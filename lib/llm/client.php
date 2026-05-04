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

        // Подготавливаем контекст каталога
        $catalogContext = '';
        if ($this->contextEnabled) {
            $catalogContext = $this->getCatalogContext();
        }

        $systemPrompt = "Ты — помощник поиска на сайте косметики. Исправляй опечатки и транслитерацию, учитывая контекст магазина. Возвращай только исправленный текст без пояснений.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Если есть контекст, добавляем его как дополнительную информацию
        if (!empty($catalogContext)) {
            $messages[] = ['role' => 'system', 'content' => "Популярные бренды и категории: {$catalogContext}"];
        }

        $messages[] = ['role' => 'user', 'content' => "Запрос: '{$query}'. Исправленный запрос:"];

        $url = $this->getApiUrl();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        $body = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 100
        ]);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return $query;
            }
            if ($httpCode !== 200) {
                return $query;
            }

            $json = json_decode($response, true);
            $corrected = trim($json['choices'][0]['message']['content'] ?? $query);
            return $corrected;
        } catch (\Exception $e) {
            return $query;
        }
    }

    /**
     * Возвращает строку с популярными названиями брендов/категорий из инфоблока.
     * Кешируется на 24 часа.
     */
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
}