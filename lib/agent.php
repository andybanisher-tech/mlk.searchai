<?php
namespace Mlk\Searchai;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Mlk\Searchai\Search\Embedder;

class Agent
{
    public static function cleanOldData(): string
    {
        $connection = Application::getConnection();

        $connection->queryExecute("
            DELETE FROM b_searchai_phrases 
            WHERE LAST_SEARCH_TIME < DATE_SUB(NOW(), INTERVAL 90 DAY) 
              AND COUNT < 5
        ");
        $connection->queryExecute("
            DELETE pr FROM b_searchai_phrase_relations pr
            LEFT JOIN b_searchai_phrases p1 ON pr.PHRASE_ID = p1.ID
            LEFT JOIN b_searchai_phrases p2 ON pr.RELATED_PHRASE_ID = p2.ID
            WHERE p1.ID IS NULL OR p2.ID IS NULL
        ");
        $connection->queryExecute("
            DELETE FROM b_searchai_llm_log 
            WHERE TIMESTAMP < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return '\\Mlk\\Searchai\\Agent::cleanOldData();';
    }

    /**
     * Фоновая генерация эмбеддингов — по 50 товаров за один запуск.
     * Возвращает своё имя, чтобы перезапускаться, пока не закончит.
     */
    public static function generateEmbeddings(): string
    {
        $moduleId = 'mlk.searchai';
        $iblockId = (int)Option::get($moduleId, 'iblock_id', 2);
        $batchSize = 50;

        $connection = Application::getConnection();

        // Получаем список ID товаров, у которых ещё нет эмбеддинга
        $sql = "SELECT e.ID, e.NAME, e.DETAIL_TEXT, e.PREVIEW_TEXT
                FROM b_iblock_element e
                LEFT JOIN b_searchai_embeddings emb ON e.ID = emb.ITEM_ID
                WHERE e.IBLOCK_ID = {$iblockId}
                  AND e.ACTIVE = 'Y'
                  AND emb.ITEM_ID IS NULL
                LIMIT {$batchSize}";

        $res = $connection->query($sql);
        $count = 0;

        while ($el = $res->fetch()) {
            $text = $el['NAME'];
            if (!empty($el['DETAIL_TEXT'])) {
                $text .= ' ' . $el['DETAIL_TEXT'];
            } elseif (!empty($el['PREVIEW_TEXT'])) {
                $text .= ' ' . $el['PREVIEW_TEXT'];
            }
            $emb = Embedder::getEmbedding($text);
            if ($emb) {
                Embedder::saveEmbedding($el['ID'], $emb);
                $count++;
            }
        }

        // Если обработали 0 товаров — конец, иначе продолжаем
        if ($count == 0) {
            // Удаляем агента
            \CAgent::RemoveAgent(
                '\\Mlk\\Searchai\\Agent::generateEmbeddings();',
                'mlk.searchai'
            );
            return '';
        }

        // Перезапустить через 10 секунд
        return '\\Mlk\\Searchai\\Agent::generateEmbeddings();';
    }
}