<?php

namespace YourPartnerCode\SearchAi\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;

class SearchController
{
    public function handleSearch(array $params): array
    {
        global $USER;
        
        if (!$USER->IsAuthorized()) {
            return [
                'success' => false,
                'error' => 'Access denied'
            ];
        }

        $query = trim($params['query'] ?? '');
        $limit = intval($params['limit'] ?? 10);
        $siteId = $params['site_id'] ?? SITE_ID;

        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Query is required'
            ];
        }

        $indexer = new \YourPartnerCode\SearchAi\Search\Indexer();
        $results = $indexer->search($query, $limit, $siteId);

        return [
            'success' => true,
            'data' => $results,
            'query' => $query
        ];
    }

    public function handleSuggest(array $params): array
    {
        global $USER;
        
        if (!$USER->IsAuthorized()) {
            return [
                'success' => false,
                'error' => 'Access denied'
            ];
        }

        $partialQuery = trim($params['partial_query'] ?? '');

        if (empty($partialQuery)) {
            return [
                'success' => false,
                'error' => 'Partial query is required'
            ];
        }

        $suggester = new \YourPartnerCode\SearchAi\Search\Suggester();
        $suggestions = $suggester->getSuggestions($partialQuery);

        return [
            'success' => true,
            'data' => $suggestions
        ];
    }
}
