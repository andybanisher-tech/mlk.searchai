<?php
namespace Mlk\Searchai\Search;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class ProductSearchHelper
{
    /**
     * Выполняет поиск товаров с учётом всех настроек модуля.
     *
     * @param int $iblockId
     * @param string $query
     * @param int $limit
     * @param string $moduleId
     * @param array $prioritizedIds массив ID для приоритетной сортировки (персонализация)
     * @param array|null $selectFields если нужны дополнительные поля
     * @return array
     */
    public static function search(int $iblockId, string $query, int $limit, string $moduleId, array $prioritizedIds = [], ?array $selectFields = null): array
    {
        $searchFields = Option::get($moduleId, 'search_fields', 'NAME,CODE,PROPERTY_ARTICLE');
        $fields = array_map('trim', explode(',', $searchFields));

        $filterActive         = Option::get($moduleId, 'filter_active', 'Y') === 'Y';
        $filterUseCatalog     = Option::get($moduleId, 'filter_use_catalog', 'N') === 'Y';
        $filterAvailable      = Option::get($moduleId, 'filter_available', 'Y') === 'Y';
        $filterQuantityNotZero = Option::get($moduleId, 'filter_quantity_not_zero', 'N') === 'Y';

        $filter = ['IBLOCK_ID' => $iblockId];
        if ($filterActive) {
            $filter['ACTIVE'] = 'Y';
            $filter['ACTIVE_DATE'] = 'Y';
        }

        $subFilter = ['LOGIC' => 'OR'];
        foreach ($fields as $field) {
            $subFilter["%{$field}"] = $query;
        }
        $filter[] = $subFilter;

        // Каталоговые фильтры
        if ($filterUseCatalog && Loader::includeModule('catalog') && ($filterAvailable || $filterQuantityNotZero)) {
            $catalogFilter = [];
            if ($filterAvailable) {
                $catalogFilter['AVAILABLE'] = 'Y';
            }
            if ($filterQuantityNotZero) {
                $catalogFilter['>QUANTITY'] = 0;
            }
            if (!empty($catalogFilter)) {
                $productIds = [];
                $res = \CCatalogProduct::GetList([], $catalogFilter, false, false, ['ID']);
                while ($row = $res->Fetch()) {
                    $productIds[] = $row['ID'];
                }
                if (!empty($productIds)) {
                    $filter['ID'] = $productIds;
                } else {
                    $filter['ID'] = -1;
                }
            }
        }

        // Выбор полей
        if ($selectFields === null) {
            $select = ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE'];
            foreach ($fields as $field) {
                if (strpos($field, 'PROPERTY_') === 0) {
                    $select[] = $field . '_VALUE';
                } else {
                    $select[] = $field;
                }
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
        while ($element = $elements->GetNext()) {
            $image = '';
            if (isset($element['PREVIEW_PICTURE']) && $element['PREVIEW_PICTURE']) {
                $image = \CFile::GetPath($element['PREVIEW_PICTURE']);
            }
            $item = [
                'id'    => $element['ID'],
                'name'  => $element['NAME'],
                'url'   => $element['DETAIL_PAGE_URL'],
                'image' => $image,
            ];
            // Дополнительные поля, если переданы
            foreach ($select as $sel) {
                if (strpos($sel, 'PROPERTY_') === 0) {
                    $value = $element[$sel . '_VALUE'] ?? '';
                    if (!empty($value)) {
                        $item['article'] = $value;
                        break;
                    }
                }
            }
            // Если запрошены все поля сниппетов (для AI), добавляем сырые данные
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
        }

        // Приоритетная сортировка
        if (!empty($prioritizedIds)) {
            usort($allResults, function ($a, $b) use ($prioritizedIds) {
                $aP = in_array($a['id'], $prioritizedIds) ? 0 : 1;
                $bP = in_array($b['id'], $prioritizedIds) ? 0 : 1;
                return $aP <=> $bP;
            });
        }

        return array_slice($allResults, 0, $limit);
    }
}