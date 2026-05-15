<?php
namespace Mlk\Searchai\Search;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

class ProductSearchHelper
{
    public static function search(int $iblockId, string $query, int $limit, string $moduleId, array $prioritizedIds = [], ?array $selectFields = null): array
    {
        $searchFields = Option::get($moduleId, 'search_fields', 'NAME,CODE,PROPERTY_ARTICLE');
        $fields = array_map('trim', explode(',', $searchFields));

        $filterActive = Option::get($moduleId, 'filter_active', 'Y') === 'Y';
        $filterUseCatalog = Option::get($moduleId, 'filter_use_catalog', 'N') === 'Y';
        $filterAvailable = Option::get($moduleId, 'filter_available', 'Y') === 'Y';
        $filterQuantityNotZero = Option::get($moduleId, 'filter_quantity_not_zero', 'N') === 'Y';

        $filter = ['IBLOCK_ID' => $iblockId];
        if ($filterActive) {
            $filter['ACTIVE'] = 'Y';
            $filter['ACTIVE_DATE'] = 'Y';
        }

        // Полнотекстовый поиск
        $fulltextIds = self::performFulltextSearch($iblockId, $query, $fields, $filter);
        if ($fulltextIds !== null) {
            $propertyIds = self::performLikeSearch($iblockId, $query, $fields, $filter);
            $mergedIds = array_unique(array_merge($fulltextIds, $propertyIds));
            if (empty($mergedIds)) {
                return [];
            }
            $filter['ID'] = $mergedIds;
        } else {
            $subFilter = ['LOGIC' => 'OR'];
            foreach ($fields as $field) {
                $subFilter["%{$field}"] = $query;
            }
            $filter[] = $subFilter;
        }

        // Каталоговые фильтры
        if ($filterUseCatalog && Loader::includeModule('catalog') && ($filterAvailable || $filterQuantityNotZero)) {
            $catalogFilter = [];
            if ($filterAvailable) $catalogFilter['AVAILABLE'] = 'Y';
            if ($filterQuantityNotZero) $catalogFilter['>QUANTITY'] = 0;
            if (!empty($catalogFilter)) {
                $productIds = [];
                $res = \CCatalogProduct::GetList([], $catalogFilter, false, false, ['ID']);
                while ($row = $res->Fetch()) $productIds[] = $row['ID'];
                if (!empty($productIds)) {
                    $filter['ID'] = !empty($filter['ID']) ? array_intersect($filter['ID'], $productIds) : $productIds;
                } else {
                    $filter['ID'] = -1;
                }
            }
        }

        // Выбор полей
        if ($selectFields === null) {
            $select = ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE'];
            foreach ($fields as $field) {
                $select[] = (strpos($field, 'PROPERTY_') === 0) ? $field . '_VALUE' : $field;
            }
        } else {
            $select = $selectFields;
        }

        $elements = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => $limit * 3],
            $select
        );

        $allResults = [];
        $itemIds = [];
        while ($element = $elements->GetNext()) {
            $image = '';
            if (!empty($element['PREVIEW_PICTURE'])) {
                $image = \CFile::GetPath($element['PREVIEW_PICTURE']);
            }
            $item = [
                'id' => $element['ID'],
                'name' => $element['NAME'],
                'url' => $element['DETAIL_PAGE_URL'],
                'image' => $image,
            ];
            foreach ($select as $sel) {
                if (strpos($sel, 'PROPERTY_') === 0) {
                    $value = $element[$sel . '_VALUE'] ?? '';
                    if (!empty($value)) {
                        $item['article'] = $value;
                        break;
                    }
                }
            }
            if ($selectFields !== null) {
                foreach ($selectFields as $sel) {
                    if (isset($element[$sel])) {
                        $item[$sel] = $element[$sel];
                    } elseif (strpos($sel, 'PROPERTY_') === 0) {
                        $item[$sel] = $element[$sel . '_VALUE'] ?? '';
                    }
                }
            }
            $allResults[] = $item;
            $itemIds[] = $element['ID'];
        }

        // Сортировка по популярности, если задано свойство
        $sortPropCode = Option::get($moduleId, 'sort_property', '');
        if (!empty($sortPropCode) && !empty($itemIds)) {
            $connection = Application::getConnection();
            $idsStr = implode(',', $itemIds);
            $sql = "SELECT IBLOCK_ELEMENT_ID, VALUE_NUM, VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID IN ({$idsStr}) AND IBLOCK_PROPERTY_ID = (SELECT ID FROM b_iblock_property WHERE IBLOCK_ID = {$iblockId} AND CODE = '" . $connection->getSqlHelper()->forSql($sortPropCode) . "')";
            $rows = $connection->query($sql);
            $propValues = [];
            while ($row = $rows->fetch()) {
                $val = $row['VALUE_NUM'] !== null && $row['VALUE_NUM'] !== '' ? (float)$row['VALUE_NUM'] : (float)$row['VALUE'];
                $propValues[$row['IBLOCK_ELEMENT_ID']] = $val;
            }
            // Сортируем товары по убыванию значения
            usort($allResults, function ($a, $b) use ($propValues) {
                $valA = $propValues[$a['id']] ?? 0;
                $valB = $propValues[$b['id']] ?? 0;
                return $valB <=> $valA;
            });
        }

        // Приоритетная сортировка (персонализация) — теперь стабильная, чтобы не ломать предыдущую сортировку
        if (!empty($prioritizedIds)) {
            // Разбиваем на две группы: приоритетные и остальные
            $prioritized = [];
            $nonPrioritized = [];
            foreach ($allResults as $item) {
                if (in_array($item['id'], $prioritizedIds)) {
                    $prioritized[] = $item;
                } else {
                    $nonPrioritized[] = $item;
                }
            }
            // Соединяем группы (приоритетные идут первыми)
            $allResults = array_merge($prioritized, $nonPrioritized);
        }

        return array_slice($allResults, 0, $limit);
    }

    private static function performFulltextSearch(int $iblockId, string $query, array $fields, array $baseFilter): ?array
    {
        $textFields = array_intersect($fields, ['NAME', 'DETAIL_TEXT', 'PREVIEW_TEXT']);
        if (empty($textFields)) {
            return null;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        try {
            $matchColumns = array_map(function($f) { return 'e.' . $f; }, $textFields);
            $matchExpression = "MATCH(" . implode(',', $matchColumns) . ") AGAINST('" . $helper->forSql($query) . "*' IN BOOLEAN MODE)";

            $sql = "SELECT e.ID FROM b_iblock_element e WHERE e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'";
            if (isset($baseFilter['ACTIVE_DATE']) && $baseFilter['ACTIVE_DATE'] == 'Y') {
                $sql .= " AND (e.ACTIVE_TO >= NOW() OR e.ACTIVE_TO IS NULL)";
            }
            $sql .= " AND {$matchExpression} LIMIT 100";

            $rows = $connection->query($sql)->fetchAll();
            return empty($rows) ? [] : array_column($rows, 'ID');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function performLikeSearch(int $iblockId, string $query, array $fields, array $baseFilter): array
    {
        $likeFields = array_filter($fields, function($f) {
            return !in_array($f, ['NAME', 'DETAIL_TEXT', 'PREVIEW_TEXT']);
        });
        if (empty($likeFields)) {
            return [];
        }

        $filter = ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'];
        $subFilter = ['LOGIC' => 'OR'];
        foreach ($likeFields as $field) {
            $subFilter["%{$field}"] = $query;
        }
        $filter[] = $subFilter;

        $ids = [];
        $res = \CIBlockElement::GetList([], $filter, false, false, ['ID']);
        while ($el = $res->Fetch()) {
            $ids[] = $el['ID'];
        }
        return $ids;
    }
}