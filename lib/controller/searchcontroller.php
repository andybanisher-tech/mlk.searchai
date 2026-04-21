<?php
namespace YourPartner\Searchai\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ORM\Query\Query;
use YourPartner\Searchai\Search\Indexer;
use YourPartner\Searchai\Search\Suggester;
use YourPartner\Searchai\Llm\Client;

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
                'postfilters' => []
            ],
        ];
    }

    public function liveSearchAction($query, $limit = 5)
    {
        Loader::includeModule('iblock');
        
        // 1. Корректируем запрос через LLM (если включено)
        $llmClient = new Client();
        $correctedQuery = $llmClient->correctQuery($query);
        
        // 2. Выполняем поиск в инфоблоке
        $searchResults = $this->searchInIblock($correctedQuery, $limit);
        
        // 3. Получаем подсказки для продолжения запроса
        $suggester = new Suggester();
        $suggestions = $suggester->getSuggestions($correctedQuery);
        
        // 4. Логируем запрос
        $this->logSearchQuery($query, $correctedQuery);
        
        return new Json([
            'status' => 'success',
            'query' => $query,
            'correctedQuery' => $correctedQuery,
            'results' => $searchResults,
            'suggestions' => $suggestions
        ]);
    }
    
    protected function searchInIblock($query, $limit)
    {
        // Используем стандартный API для поиска в инфоблоке
        // В будущем добавим гибкую настройку полей и свойств
        $iblockId = \Bitrix\Main\Config\Option::get($this->getModuleId(), 'iblock_id', 0);
        if (!$iblockId) {
            return [];
        }
        
        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            [
                'LOGIC' => 'OR',
                ['%NAME' => $query],
                ['%CODE' => $query],
                ['%PROPERTY_ARTICLE' => $query],
                // Добавим позже динамические свойства из настроек
            ]
        ];
        
        $select = ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'PROPERTY_ARTICLE'];
        
        $elements = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => $limit],
            $select
        );
        
        $results = [];
        while ($element = $elements->GetNext()) {
            $results[] = [
                'id' => $element['ID'],
                'name' => $element['NAME'],
                'url' => $element['DETAIL_PAGE_URL'],
                'image' => \CFile::GetPath($element['PREVIEW_PICTURE']),
                'article' => $element['PROPERTY_ARTICLE_VALUE'],
            ];
        }
        
        return $results;
    }
    
    protected function logSearchQuery($originalQuery, $correctedQuery)
    {
        // Сохраняем статистику в таблицу b_searchai_phrases
        // ...
    }
}