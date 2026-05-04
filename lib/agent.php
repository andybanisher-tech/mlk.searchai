<?php
namespace Mlk\Searchai;

use Bitrix\Main\Application;

class Agent
{
    /**
     * Агент очистки старых поисковых фраз и связей.
     * Удаляет записи из b_searchai_phrases старше 90 дней с низкой частотой.
     * Также очищает логи LLM старше 30 дней.
     * @return string
     */
    public static function cleanOldData(): string
    {
        $connection = Application::getConnection();

        // Удаляем фразы, которые не искали более 90 дней и у которых счётчик меньше 5
        $connection->queryExecute("
            DELETE FROM b_searchai_phrases 
            WHERE LAST_SEARCH_TIME < DATE_SUB(NOW(), INTERVAL 90 DAY) 
              AND COUNT < 5
        ");

        // Удаляем связи, связанные с несуществующими фразами
        $connection->queryExecute("
            DELETE pr FROM b_searchai_phrase_relations pr
            LEFT JOIN b_searchai_phrases p1 ON pr.PHRASE_ID = p1.ID
            LEFT JOIN b_searchai_phrases p2 ON pr.RELATED_PHRASE_ID = p2.ID
            WHERE p1.ID IS NULL OR p2.ID IS NULL
        ");

        // Очищаем старые логи LLM
        $connection->queryExecute("
            DELETE FROM b_searchai_llm_log 
            WHERE TIMESTAMP < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        // Обрезаем связи, если их всё ещё слишком много (более 20 у одного слова)
        // Это делается через отдельный запрос, но оставим как есть, addRelation уже ограничивает.

        return '\\Mlk\\Searchai\\Agent::cleanOldData();';
    }
}