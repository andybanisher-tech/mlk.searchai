<?php
namespace Mlk\Searchai\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;

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

        // Заглушка – тестовые результаты
        $testResults = [
            [
                'id' => 1,
                'name' => 'Тестовый товар "Молочко для тела"',
                'url' => '/catalog/test1/',
                'image' => '',
                'article' => 'ART001',
            ],
            [
                'id' => 2,
                'name' => 'Крем для лица увлажняющий',
                'url' => '/catalog/test2/',
                'image' => '',
                'article' => 'ART002',
            ],
        ];

        // Фильтруем по запросу (примитивно)
        $filtered = array_filter($testResults, function($item) use ($query) {
            return mb_stripos($item['name'], $query) !== false;
        });

        $suggestions = ['для тела', 'для лица']; // заглушка подсказок

        return new Json([
            'status' => 'success',
            'query' => $query,
            'correctedQuery' => $query,
            'results' => array_values($filtered),
            'suggestions' => $suggestions,
        ]);
    }
}