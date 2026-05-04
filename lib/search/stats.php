<?php

namespace Mlk\Searchai\Search;

use Bitrix\Main\Application;
use Bitrix\Main\UserGroupTable;

class Stats
{
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
     * Возвращает подсказки для последнего слова запроса.
     * Исключает подсказки, слова из которых уже есть в запросе.
     */
     public static function getSuggestions(string $query, ?int $userId = null): array
    {
        $words = explode(' ', $query);
        $contextWord = end($words);
        if (empty($contextWord) || mb_strlen($contextWord) < 3) {
            return [];
        }

        $queryWordsLower = array_map('mb_strtolower', $words);
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $contextEsc = $helper->forSql($contextWord);
        $suggestions = [];

        // Определяем группы текущего пользователя (если авторизован)
        $userGroupIds = [];
        if ($userId > 0 && Loader::includeModule('main')) {
            $groups = UserGroupTable::getList([
                'filter' => ['=USER_ID' => $userId],
                'select' => ['GROUP_ID'],
            ]);
            foreach ($groups as $group) {
                $userGroupIds[] = (int)$group['GROUP_ID'];
            }
        }

        // 1. Маркетинговые подсказки с учётом групп
        $sqlPromo = "SELECT SUGGESTION, USER_GROUPS FROM b_searchai_promoted_suggestions 
                     WHERE KEYWORD = '{$contextEsc}' AND ACTIVE = 'Y'
                     ORDER BY WEIGHT DESC LIMIT 10";
        $promoRes = $connection->query($sqlPromo);
        $addedPromo = 0;
        while ($row = $promoRes->fetch()) {
            // Проверяем, разрешена ли подсказка для текущего пользователя
            $allowed = true;
            if (!empty($row['USER_GROUPS']) && !empty($userGroupIds)) {
                $allowGroups = explode(',', $row['USER_GROUPS']);
                $intersect = array_intersect($allowGroups, $userGroupIds);
                // Если пользователь авторизован и не входит ни в одну из разрешённых групп – пропускаем
                if (!empty($userGroupIds) && empty($intersect)) {
                    $allowed = false;
                }
            }
            if ($allowed && !in_array($row['SUGGESTION'], $suggestions) && mb_strlen($row['SUGGESTION']) >= 3) {
                $suggestions[] = $row['SUGGESTION'];
                $addedPromo++;
            }
            if ($addedPromo >= 3) break;
        }

        // 2. Статистические связи с учётом персональной истории (если пользователь авторизован)
        if (count($suggestions) < 3) {
            // Сначала попробуем найти персональные связи
            if ($userId > 0) {
                $sqlPersonal = "SELECT p2.PHRASE as NEXT_WORD
                         FROM b_searchai_phrase_relations pr
                         JOIN b_searchai_phrases p1 ON pr.PHRASE_ID = p1.ID
                         JOIN b_searchai_phrases p2 ON pr.RELATED_PHRASE_ID = p2.ID
                         WHERE p1.PHRASE = '{$contextEsc}' 
                           AND p2.USER_ID = {$userId}
                         ORDER BY pr.WEIGHT DESC, p2.COUNT DESC
                         LIMIT " . (3 - count($suggestions));
                $personalRes = $connection->query($sqlPersonal);
                while ($row = $personalRes->fetch()) {
                    if (!in_array($row['NEXT_WORD'], $suggestions)) {
                        $suggestions[] = $row['NEXT_WORD'];
                    }
                }
            }

            // Если персональных не хватило, добираем общие
            if (count($suggestions) < 3) {
                $sqlStats = "SELECT p2.PHRASE as NEXT_WORD
                         FROM b_searchai_phrase_relations pr
                         JOIN b_searchai_phrases p1 ON pr.PHRASE_ID = p1.ID
                         JOIN b_searchai_phrases p2 ON pr.RELATED_PHRASE_ID = p2.ID
                         WHERE p1.PHRASE = '{$contextEsc}'
                         ORDER BY pr.WEIGHT DESC, p2.COUNT DESC
                         LIMIT " . (3 - count($suggestions));
                $statsRes = $connection->query($sqlStats);
                while ($row = $statsRes->fetch()) {
                    if (!in_array($row['NEXT_WORD'], $suggestions)) {
                        $suggestions[] = $row['NEXT_WORD'];
                    }
                }
            }
        }

        return array_values(array_filter($suggestions, function($sug) use ($queryWordsLower, $contextWord) {
            $sugLower = mb_strtolower($sug);
            if (mb_strlen($sug) < 3) return false;
            if ($sugLower === mb_strtolower($contextWord)) return false;
            if (in_array($sugLower, $queryWordsLower, true)) return false;
            return true;
        }));
    }

    /**
     * Обновляет связи между словами с учётом контекста.
     */
    public static function updateRelations(string $previousQuery, string $currentQuery): void
    {
        $isExtension = (mb_strpos($currentQuery, $previousQuery) === 0);
        $prevWords = explode(' ', $previousQuery);
        $currWords = explode(' ', $currentQuery);

        if (!$isExtension) {
            $prevLast = end($prevWords);
            $currFirst = reset($currWords);
            if (!empty($prevLast) && !empty($currFirst)) {
                self::addRelation($prevLast, $currFirst);
            }
        }

        // Связываем последовательные слова внутри текущего запроса,
        // но не создаём связь, если второе слово уже встречалось ранее в этом же запросе.
        $seen = [];
        for ($i = 0; $i < count($currWords); $i++) {
            $wordLower = mb_strtolower($currWords[$i]);
            if (!isset($seen[$wordLower])) {
                $seen[$wordLower] = $i;
            }
        }

        for ($i = 0; $i < count($currWords) - 1; $i++) {
            $wordA = $currWords[$i];
            $wordB = $currWords[$i + 1];
            if (empty($wordA) || empty($wordB)) continue;

            // Если слово B уже встречалось раньше (не на следующей позиции), не создаём связь
            $wordBLower = mb_strtolower($wordB);
            if (isset($seen[$wordBLower]) && $seen[$wordBLower] < $i) {
                continue;
            }

            self::addRelation($wordA, $wordB);
        }
    }

    protected static function addRelation(string $wordA, string $wordB): void
    {
        if (mb_strlen($wordA) < 3 || mb_strlen($wordB) < 3) return;
        if (mb_strtolower($wordA) === mb_strtolower($wordB)) return;

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $idA = self::getPhraseId($wordA);
        if (!$idA) {
            $connection->queryExecute("INSERT IGNORE INTO b_searchai_phrases (PHRASE, COUNT, LAST_SEARCH_TIME) VALUES ('" . $helper->forSql($wordA) . "', 1, NOW())");
            $idA = self::getPhraseId($wordA);
        }
        $idB = self::getPhraseId($wordB);
        if (!$idB) {
            $connection->queryExecute("INSERT IGNORE INTO b_searchai_phrases (PHRASE, COUNT, LAST_SEARCH_TIME) VALUES ('" . $helper->forSql($wordB) . "', 1, NOW())");
            $idB = self::getPhraseId($wordB);
        }

        if ($idA && $idB) {
            // Ограничение: не более 20 связей для одного слова
            $countSql = "SELECT COUNT(*) as CNT FROM b_searchai_phrase_relations WHERE PHRASE_ID = " . (int)$idA;
            $row = $connection->query($countSql)->fetch();
            if ($row['CNT'] >= 20) {
                // Удаляем самую слабую связь
                $deleteSql = "DELETE FROM b_searchai_phrase_relations 
                              WHERE PHRASE_ID = " . (int)$idA . " 
                              ORDER BY WEIGHT ASC, ID ASC LIMIT 1";
                $connection->queryExecute($deleteSql);
            }

            $sql = "INSERT INTO b_searchai_phrase_relations (PHRASE_ID, RELATED_PHRASE_ID, WEIGHT)
                    VALUES ({$idA}, {$idB}, 1)
                    ON DUPLICATE KEY UPDATE WEIGHT = WEIGHT + 1";
            $connection->queryExecute($sql);
        }
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
