<?php

namespace YourPartnerCode\SearchAi\Search;

use Bitrix\Main\Application;
use Bitrix\Main\Database\Connection;

class Suggester
{
    protected Connection $db;
    protected int $cacheLifetime = 3600;
    protected int $maxSuggestions = 5;

    public function __construct()
    {
        $this->db = Application::getConnection();
    }

    public function getSuggestions(string $partialQuery): array
    {
        $cache = Application::getMain()->getCacheManager();
        $cacheId = 'searchai_suggest_' . md5($partialQuery);

        if ($cache->initCache($this->cacheLifetime, $cacheId)) {
            $data = $cache->getVars();
            return $data['suggestions'] ?? [];
        }

        $suggestions = $this->fetchSuggestions($partialQuery);
        
        if ($cache->startDataCache()) {
            $cache->endDataCache(['suggestions' => $suggestions]);
        }

        return $suggestions;
    }

    protected function fetchSuggestions(string $partialQuery): array
    {
        $helper = $this->db->getSqlHelper();
        $searchTerm = "%{$helper->forSql($partialQuery)}%";

        $result = $this->db->queryExecute("
            SELECT DISTINCT
                SUBSTRING(CONTENT, 1, 100) as suggestion
            FROM b_searchai_index
            WHERE CONTENT LIKE :search_term
            ORDER BY DATE_UPDATE DESC
            LIMIT :limit
        ", [
            'search_term' => $searchTerm,
            'limit' => $this->maxSuggestions
        ]);

        $suggestions = [];
        while ($row = $result->fetch()) {
            $suggestions[] = strip_tags($row['SUGGESTION']);
        }

        return $suggestions;
    }

    public function updateHitCount(string $query): void
    {
        $helper = $this->db->getSqlHelper();
        
        $result = $this->db->queryExecute("
            SELECT ID FROM b_searchai_suggestions 
            WHERE QUERY = :query
        ", ['query' => $query]);

        if ($row = $result->fetch()) {
            $this->db->queryExecute("
                UPDATE b_searchai_suggestions 
                SET HIT_COUNT = HIT_COUNT + 1 
                WHERE ID = :id
            ", ['id' => $row['ID']]);
        } else {
            $this->db->queryExecute("
                INSERT INTO b_searchai_suggestions (QUERY, SUGGESTIONS, HIT_COUNT, DATE_CREATE)
                VALUES (:query, '', 1, NOW())
            ", ['query' => $query]);
        }
    }
}
