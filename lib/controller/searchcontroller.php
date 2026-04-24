<?php

namespace Mlk\Searchai\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Iblock\ElementTable;

class SearchController extends Controller
{
    public function configureActions()
    {
        return [
            'liveSearch' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(['POST']),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    public function liveSearchAction($query, $limit = 5)
    {
        Loader::includeModule('iblock');
        Loader::includeModule('mlk.searchai');

        $moduleId = 'mlk.searchai';
        $iblockId = (int)Option::get($moduleId, 'iblock_id', 2);
        $limit = (int)Option::get($moduleId, 'results_limit', 5);

        // Получаем поля для поиска из настроек
        $searchFields = Option::get($moduleId, 'search_fields', 'NAME,CODE,PROPERTY_ARTICLE');
        $fields = array_map('trim', explode(',', $searchFields));

        // Коррекция через LLM (пока заглушка, позже подключим)
        $correctedQuery = $query;

        // Строим фильтр для CIBlockElement
        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'ACTIVE_DATE' => 'Y',
        ];

        $subFilter = ['LOGIC' => 'OR'];
        foreach ($fields as $field) {
            $subFilter["%{$field}"] = $correctedQuery;
        }
        $filter[] = $subFilter;

        // Выбираем элементы
        $select = ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE'];
        foreach ($fields as $field) {
            if (strpos($field, 'PROPERTY_') === 0) {
                $select[] = $field . '_VALUE';
            } else {
                $select[] = $field;
            }
        }

        $elements = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => $limit],
            $select
        );

        $results = [];
        while ($element = $elements->GetNext()) {
            $image = '';
            if ($element['PREVIEW_PICTURE']) {
                $image = \CFile::GetPath($element['PREVIEW_PICTURE']);
            }

            $item = [
                'id' => $element['ID'],
                'name' => $element['NAME'],
                'url' => $element['DETAIL_PAGE_URL'],
                'image' => $image,
            ];

            // Добавляем значение артикула (или другого первого свойства)
            foreach ($fields as $field) {
                if (strpos($field, 'PROPERTY_') === 0) {
                    $propCode = substr($field, 9);
                    $value = $element[$field . '_VALUE'] ?? '';
                    if (!empty($value)) {
                        $item['article'] = $value;
                        break;
                    }
                }
            }
            $results[] = $item;
        }

        // Подсказки (пока заглушка)
        $suggestions = $this->getSuggestions($correctedQuery);

        // Логирование запроса
        $this->logSearchQuery($query, $correctedQuery);

        return new Json([
            'status' => 'success',
            'query' => $query,
            'correctedQuery' => $correctedQuery,
            'results' => $results,
            'suggestions' => $suggestions,
        ]);
    }

    protected function getSuggestions($query)
    {
        // Временная заглушка
        return ['для лица', 'для тела', 'увлажняющий'];
    }

    protected function logSearchQuery($originalQuery, $correctedQuery)
    {
        try {
            $connection = Application::getConnection();
            $sql = "INSERT INTO b_searchai_phrases (PHRASE, COUNT, LAST_SEARCH_TIME) 
                    VALUES ('" . $connection->getSqlHelper()->forSql($correctedQuery) . "', 1, NOW())
                    ON DUPLICATE KEY UPDATE COUNT = COUNT + 1, LAST_SEARCH_TIME = NOW()";
            $connection->queryExecute($sql);
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
    }
}
