<?php

namespace Mlk\Searchai\Llm;

use Bitrix\Main\Config\Option;

class Client
{
    protected string $provider;
    protected string $apiKey;
    protected string $model;
    protected ?string $baseUrl = null;

    public function __construct()
    {
        $moduleId = 'mlk.searchai';
        $this->provider = Option::get($moduleId, 'llm_provider', 'mistral');
        $this->apiKey   = Option::get($moduleId, 'llm_api_key', '');
        $this->model    = Option::get($moduleId, 'llm_model', 'mistral-small');
        $this->baseUrl  = Option::get($moduleId, 'llm_base_url', '');
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

        $prompt = "Исправь опечатки и транслитерацию в поисковом запросе пользователя. Ответь только исправленным текстом без пояснений.\nЗапрос: '{$query}'\nИсправленный запрос:";

        $url = $this->getApiUrl();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        $body = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
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
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return $query;
            }

            $json = json_decode($response, true);
            return trim($json['choices'][0]['message']['content'] ?? $query);
        } catch (\Exception $e) {
            return $query;
        }
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
