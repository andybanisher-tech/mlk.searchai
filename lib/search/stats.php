<?php

namespace Mlk\Searchai\Search;

use Bitrix\Main\Application;

class Stats
{
    /**
     * Логирует поисковый запрос в таблицу b_searchai_phrases.
     */
    public static function logSearch(string $query, ?int $userId = null): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $queryEsc = $helper->forSql($query);
        $userIdEsc = $userId ? (int)$userId : 'NULL';

        $sql = "INSERT INTO b_searchai_phrases (PHRASE, COUNT, LAST_SEARCH_TIME, USER_ID)
                VALUES ('{$queryEsc}', 1, NOW(), {$userIdEsc})
                ON DUPLICATE KEY UPDATE COUNT = COUNT + 1, LAST_SEARCH_TIME = NOW()";
        $connection->queryExecute($sql);
    }

    /**
     * Возвращает массив подсказок для продолжения поисковой фразы.
     */
    public static function getSuggestions(string $query): array
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $queryEsc = $helper->forSql($query);
        $suggestions = [];

        // 1. Продвигаемые (маркетинговые) подсказки
        $sqlPromo = "SELECT SUGGESTION FROM b_searchai_promoted_suggestions 
                     WHERE KEYWORD = '{$queryEsc}' AND ACTIVE = 'Y'
                     ORDER BY WEIGHT DESC LIMIT 3";
        $promoRes = $connection->query($sqlPromo);
        while ($row = $promoRes->fetch()) {
            $suggestions[] = $row['SUGGESTION'];
        }

        // 2. Статистические подсказки на основе частых связей
        if (count($suggestions) < 3) {
            $sqlStats = "SELECT p2.PHRASE as NEXT_WORD
                         FROM b_searchai_phrase_relations pr
                         JOIN b_searchai_phrases p1 ON pr.PHRASE_ID = p1.ID
                         JOIN b_searchai_phrases p2 ON pr.RELATED_PHRASE_ID = p2.ID
                         WHERE p1.PHRASE = '{$queryEsc}'
                         ORDER BY pr.WEIGHT DESC, p2.COUNT DESC
                         LIMIT " . (3 - count($suggestions));
            $statsRes = $connection->query($sqlStats);
            while ($row = $statsRes->fetch()) {
                if (!in_array($row['NEXT_WORD'], $suggestions)) {
                    $suggestions[] = $row['NEXT_WORD'];
                }
                if (count($suggestions) >= 3) break;
            }
        }

        return $suggestions;
    }

    /**
     * Фиксирует связь между двумя последовательными поисковыми фразами.
     */
    public static function updateRelations(string $previousQuery, string $currentQuery): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $prevId = self::getPhraseId($previousQuery);
        $currId = self::getPhraseId($currentQuery);
        if (!$prevId || !$currId) return;

        $sql = "INSERT INTO b_searchai_phrase_relations (PHRASE_ID, RELATED_PHRASE_ID, WEIGHT)
                VALUES ({$prevId}, {$currId}, 1)
                ON DUPLICATE KEY UPDATE WEIGHT = WEIGHT + 1";
        $connection->queryExecute($sql);
    }

    protected static function getPhraseId(string $phrase): ?int
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $phraseEsc = $helper->forSql($phrase);
        $row = $connection->query("SELECT ID FROM b_searchai_phrases WHERE PHRASE = '{$phraseEsc}'")->fetch();
        return $row ? (int)$row['ID'] : null;
    }
}
