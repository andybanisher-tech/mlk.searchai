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
        $this->provider = Option::get($moduleId, 'llm_provider', 'mistral');
        $this->apiKey   = Option::get($moduleId, 'llm_api_key', '');
        $this->model    = Option::get($moduleId, 'llm_model', 'mistral-small');
        $this->baseUrl  = Option::get($moduleId, 'llm_base_url', '');
        $this->contextEnabled = Option::get($moduleId, 'llm_context_enable', 'Y') === 'Y';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function correctQuery(string $query): string
    {
        if (!$this->isAvailable()) {
            return $query;
        }
        // ... без изменений ...
        return $this->callApi([
            ['role' => 'user', 'content' => "Исправь опечатки в запросе: '{$query}'. Верни только исправленный текст без пояснений."]
        ], 100) ?? $query;
    }

    public function analyzeSemanticQuery(string $query): string
    {
        if (!$this->isAvailable()) {
            return $query;
        }
        $promptTemplate = Option::get('mlk.searchai', 'ai_prompt_template', 'Проанализируй запрос пользователя. Твоя задача - переформулировать его в поисковый запрос, удалив лишние слова и оставив только ключевые термины, описывающие товар.');
        return $this->callApi([
            ['role' => 'system', 'content' => $promptTemplate],
            ['role' => 'user', 'content' => "Запрос: '{$query}'. Ключевые термины:"],
        ], 200) ?? $query;
    }

    public function pickProducts(string $query, array $snippets): array
    {
        if (empty($snippets) || !$this->isAvailable()) {
            return [];
        }
        // Ограничим количество сниппетов до 30, чтобы не превысить лимит токенов
        $snippets = array_slice($snippets, 0, 30, true);
        $snippetLines = [];
        foreach ($snippets as $id => $desc) {
            $shortDesc = mb_substr($desc, 0, 100);
            $snippetLines[] = "{$id}: {$shortDesc}";
        }
        $snippetText = implode("\n", $snippetLines);

        $prompt = "Пользователь ищет товар по запросу: \"{$query}\".\n"
                . "Ниже список товаров с ID и описанием. Выбери до 5 ID товаров, которые максимально соответствуют запросу.\n"
                . "Ответь ТОЛЬКО номерами ID через запятую (например: 123, 456). Не добавляй пояснений.\n\n"
                . $snippetText;

        $response = $this->callApi([['role' => 'user', 'content' => $prompt]], 300);
        // Логирование ответа
        $this->logDebug("pickProducts response: " . ($response ?? 'NULL'));
        if ($response) {
            preg_match_all('/\d+/', $response, $matches);
            return array_slice(array_unique(array_map('intval', $matches[0] ?? [])), 0, 5);
        }
        return [];
    }

    private function callApi(array $messages, int $maxTokens = 200): ?string
    {
        $url = $this->getApiUrl();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        $body = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => $maxTokens
        ]);

        $this->logDebug("callApi: {$url} model: {$this->model} messages: " . json_encode($messages));

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $this->logDebug("callApi httpCode: {$httpCode} error: {$error} response: " . ($response ?? 'NULL'));

            if ($error || $httpCode !== 200) {
                return null;
            }
            $json = json_decode($response, true);
            return trim($json['choices'][0]['message']['content'] ?? '');
        } catch (\Exception $e) {
            $this->logDebug("callApi exception: " . $e->getMessage());
            return null;
        }
    }

    private function logDebug(string $message): void
    {
        $logFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/mlk_ai_debug.log";
        file_put_contents($logFile, date("Y-m-d H:i:s") . " {$message}\n", FILE_APPEND);
    }

    protected function getApiUrl(): string
    {
        if ($this->provider === 'custom' && !empty($this->baseUrl)) {
            return rtrim($this->baseUrl, '/') . '/v1/chat/completions';
        }
        if ($this->provider === 'groq') {
            return 'https://api.groq.com/openai/v1/chat/completions';
        }
        return 'https://api.mistral.ai/v1/chat/completions';
    }

    protected function getCatalogContext(): string
    {
        $cache = Cache::createInstance();
        $cacheId = 'mlk_searchai_catalog_context';
        $cacheDir = '/mlk/searchai/context';
        $cacheTime = 86400; // 24 часа

        if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
            $result = $cache->getVars();
            return $result['context'] ?? '';
        } elseif ($cache->startDataCache()) {
            $iblockId = (int)Option::get('mlk.searchai', 'iblock_id', 0);
            if ($iblockId <= 0) {
                $cache->abortDataCache();
                return '';
            }

            // Получаем топ-50 уникальных значений свойства "Бренд" (код BRAND) или названий товаров
            $brands = [];
            // Пытаемся получить значения свойства с кодом BRAND (если есть)
            $propertyRes = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'BRAND']);
            if ($prop = $propertyRes->Fetch()) {
                $res = \CIBlockElement::GetList(
                    ['NAME' => 'ASC'],
                    ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', '!PROPERTY_BRAND' => false],
                    false,
                    ['nTopCount' => 50],
                    ['ID', 'PROPERTY_BRAND']
                );
                while ($row = $res->Fetch()) {
                    if (!empty($row['PROPERTY_BRAND_VALUE'])) {
                        $brands[] = $row['PROPERTY_BRAND_VALUE'];
                    }
                }
            }

            // Если брендов нет, берём просто названия товаров
            if (empty($brands)) {
                $res = \CIBlockElement::GetList(
                    ['NAME' => 'ASC'],
                    ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                    false,
                    ['nTopCount' => 50],
                    ['NAME']
                );
                while ($row = $res->Fetch()) {
                    $brands[] = $row['NAME'];
                }
            }

            $context = implode(', ', array_unique($brands));
            if (empty($context)) {
                $cache->abortDataCache();
                return '';
            }

            $cache->endDataCache(['context' => $context]);
            return $context;
        }

        return '';
    }
}