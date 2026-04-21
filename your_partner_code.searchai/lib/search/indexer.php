<?php

namespace YourPartnerCode\SearchAi\Search;

use Bitrix\Main\Application;
use Bitrix\Main\Database\Connection;

class Indexer
{
    protected Connection $db;

    public function __construct()
    {
        $this->db = Application::getConnection();
    }

    public function indexElement(int $elementId, string $siteId, string $content): bool
    {
        $helper = $this->db->getSqlHelper();
        
        $this->db->queryExecute("
            INSERT INTO b_searchai_index (ELEMENT_ID, SITE_ID, CONTENT, DATE_CREATE, DATE_UPDATE)
            VALUES (:element_id, :site_id, :content, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                CONTENT = :content,
                DATE_UPDATE = NOW()
        ", [
            'element_id' => $elementId,
            'site_id' => $siteId,
            'content' => $content
        ]);

        return true;
    }

    public function search(string $query, int $limit = 10, string $siteId = ''): array
    {
        $helper = $this->db->getSqlHelper();
        $siteFilter = !empty($siteId) ? "AND SITE_ID = " . $helper->forSql($siteId) : "";
        $searchTerm = "%{$helper->forSql($query)}%";

        $result = $this->db->queryExecute("
            SELECT 
                ID,
                ELEMENT_ID,
                SITE_ID,
                CONTENT,
                DATE_CREATE,
                DATE_UPDATE
            FROM b_searchai_index
            WHERE CONTENT LIKE :search_term {$siteFilter}
            ORDER BY DATE_UPDATE DESC
            LIMIT :limit
        ", [
            'search_term' => $searchTerm,
            'limit' => $limit
        ]);

        $results = [];
        while ($row = $result->fetch()) {
            $results[] = [
                'id' => $row['ID'],
                'element_id' => $row['ELEMENT_ID'],
                'site_id' => $row['SITE_ID'],
                'content' => strip_tags($row['CONTENT']),
                'date_create' => $row['DATE_CREATE'],
                'date_update' => $row['DATE_UPDATE']
            ];
        }

        return $results;
    }

    public function rebuildIndex(string $siteId = ''): int
    {
        $this->clearIndex($siteId);
        
        $count = 0;
        
        // Implement IBElement::GetList() to index elements
        // This is a placeholder for actual implementation
        
        return $count;
    }

    public function clearIndex(string $siteId = ''): bool
    {
        if (empty($siteId)) {
            $this->db->queryExecute("TRUNCATE TABLE b_searchai_index");
        } else {
            $helper = $this->db->getSqlHelper();
            $this->db->queryExecute(
                "DELETE FROM b_searchai_index WHERE SITE_ID = :site_id",
                ['site_id' => $siteId]
            );
        }
        
        return true;
    }
}
