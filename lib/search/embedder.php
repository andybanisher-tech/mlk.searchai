<?php
namespace Mlk\Searchai\Search;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

class Embedder
{
    /**
     * Генерирует эмбеддинг для текста через LLM-сервер.
     */
    public static function getEmbedding(string $text): ?array
    {
        $url = rtrim(Option::get('mlk.searchai', 'llm_base_url', 'http://31.76.227.1:8000'), '/') . '/v1/embeddings';
        $model = Option::get('mlk.searchai', 'llm_model', 'cotype-nano-Q4_K_M.gguf');
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'input' => mb_substr($text, 0, 500)
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['data'][0]['embedding'] ?? null;
    }

    /**
     * Косинусное сходство между двумя векторами.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        if ($normA == 0.0 || $normB == 0.0) return 0.0;
        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Сохраняет эмбеддинг для товара в БД.
     */
    public static function saveEmbedding(int $itemId, array $embedding): void
    {
        $connection = Application::getConnection();
        $sql = "INSERT INTO b_searchai_embeddings (ITEM_ID, EMBEDDING, UPDATED_AT) 
                VALUES ({$itemId}, '" . json_encode($embedding) . "', NOW())
                ON DUPLICATE KEY UPDATE EMBEDDING = VALUES(EMBEDDING), UPDATED_AT = NOW()";
        $connection->queryExecute($sql);
    }

    /**
     * Получает сохранённый эмбеддинг товара.
     */
    public static function getSavedEmbedding(int $itemId): ?array
    {
        $connection = Application::getConnection();
        $row = $connection->query("SELECT EMBEDDING FROM b_searchai_embeddings WHERE ITEM_ID = {$itemId}")->fetch();
        return $row ? json_decode($row['EMBEDDING'], true) : null;
    }
}