<?php
namespace Mlk\Searchai\Llm;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

class Client
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;
    protected ?string $baseUrl = null;
    protected bool $contextEnabled;

    public function __construct()
    {
        $moduleId = 'mlk.searchai';
        $this->provider = Option::get($moduleId, 'llm_provider', 'local');
        $this->apiKey   = Option::get($moduleId, 'llm_api_key', '');
        $this->model    = Option::get($moduleId, 'llm_model', 'cotype-nano-Q4_K_M.gguf');
        $this->baseUrl  = Option::get($moduleId, 'llm_base_url', 'http://31.76.227.1:8000');
        $this->contextEnabled = Option::get($moduleId, 'llm_context_enable', 'Y') === 'Y';
    }

    public function isAvailable(): bool
    {
        return $this->provider === 'local' || !empty($this->apiKey);
    }

    /**
     * Исправление опечаток (существующий метод)
     */
    public function correctQuery(string $query): string
    {
        if (!$this->isAvailable()) {
            return $query;
        }

        $prompt = "Исправь опечатки и транслитерацию в поисковом запросе. Верни только исправленный текст без пояснений.\nЗапрос: '{$query}'\nИсправленный запрос:";
        $messages = [['role' => 'user', 'content' => $prompt]];

        return $this->callApi($messages) ?? $query;
    }

    /**
     * AI-поиск: извлечение ключевых терминов из запроса
     */
    public function analyzeSemanticQuery(string $query): string
    {
        if (!$this->isAvailable()) {
            return $query;
        }

        $prompt = "Извлеки из запроса ключевые слова для поиска товаров. Верни только ключевые слова через пробел, без пояснений.\nЗапрос: '{$query}'\nКлючевые слова:";
        $messages = [['role' => 'user', 'content' => $prompt]];

        return $this->callApi($messages) ?? $query;
    }

    /**
     * AI-поиск: выбор подходящих товаров из списка сниппетов
     */
   public function pickProducts(string $query, array $snippets): array
{
    if (empty($snippets) || !$this->isAvailable()) {
        return [];
    }

    // Ограничиваем 10 сниппетами
    $snippets = array_slice($snippets, 0, 10, true);
    $snippetLines = [];
    foreach ($snippets as $id => $desc) {
        $shortDesc = mb_substr($desc, 0, 80);
        $snippetLines[] = "{$id} {$shortDesc}";
    }
    $snippetText = implode("\n", $snippetLines);

    $prompt = "Запрос: {$query}\nТовары (ID описание):\n{$snippetText}\nВыбери подходящие ID через запятую (только цифры):";
    
    $response = $this->callApi([['role' => 'user', 'content' => $prompt]], 150);
    
    // Логируем ответ
    $logFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/mlk_llm_debug.log";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " pickProducts response: " . ($response ?? 'NULL') . "\n", FILE_APPEND);

    if ($response) {
        // Извлекаем все числа из ответа
        preg_match_all('/\d+/', $response, $matches);
        $ids = array_unique(array_map('intval', $matches[0] ?? []));
        // Логируем извлечённые ID
        file_put_contents($logFile, date("Y-m-d H:i:s") . " extracted IDs: " . implode(',', $ids) . "\n", FILE_APPEND);
        return array_slice($ids, 0, 5);
    }
    return [];
}

    /**
     * Рераркинг кандидатов (гибридный поиск)
     */
    public function rerankProducts(string $query, array $candidates): array
    {
        if (empty($candidates) || !$this->isAvailable()) {
            return [];
        }

        $candidateText = "";
        foreach ($candidates as $c) {
            $candidateText .= "ID {$c['id']}: {$c['name']}. {$c['snippet']}\n";
        }

        $prompt = "Пользователь ищет: \"{$query}\".\nТовары:\n{$candidateText}\nОтсортируй ID товаров по релевантности (наиболее подходящие сначала). Ответь только ID через запятую, без пояснений.";
        $messages = [['role' => 'user', 'content' => $prompt]];
        $response = $this->callApi($messages);

        if ($response) {
            preg_match_all('/\d+/', $response, $matches);
            $orderedIds = array_unique(array_map('intval', $matches[0] ?? []));
            $result = [];
            foreach ($orderedIds as $id) {
                foreach ($candidates as $c) {
                    if ($c['id'] == $id) {
                        $result[] = $c;
                        break;
                    }
                }
            }
            return $result;
        }
        return $candidates;
    }

    /**
     * Общий метод вызова API (OpenAI-совместимый)
     */
    private function callApi(array $messages, int $maxTokens = 200): ?string
    {
        $url = $this->getApiUrl();
        $headers = [
            'Content-Type: application/json',
        ];
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        $body = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => $maxTokens
        ]);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                return null;
            }

            $json = json_decode($response, true);
            return trim($json['choices'][0]['message']['content'] ?? '');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getApiUrl(): string
    {
        if ($this->provider === 'local') {
            return rtrim($this->baseUrl, '/') . '/v1/chat/completions';
        }
        if ($this->provider === 'custom' && !empty($this->baseUrl)) {
            return rtrim($this->baseUrl, '/') . '/v1/chat/completions';
        }
        if ($this->provider === 'groq') {
            return 'https://api.groq.com/openai/v1/chat/completions';
        }
        return 'https://api.mistral.ai/v1/chat/completions';
    }
}