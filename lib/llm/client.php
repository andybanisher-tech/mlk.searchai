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

        // Логирование (можно удалить после отладки)
        $this->log("Init: provider={$this->provider}, key=" . ($this->apiKey ? 'SET' : 'EMPTY') . ", model={$this->model}");
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function correctQuery(string $query): string
    {
        $this->log("correctQuery called: {$query}");

        if (!$this->isAvailable()) {
            $this->log("No API key – returning original");
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
            $this->log("Sending to {$url}");
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
                $this->log("Curl error: {$error}");
                return $query;
            }
            if ($httpCode !== 200) {
                $this->log("HTTP {$httpCode}: {$response}");
                return $query;
            }

            $json = json_decode($response, true);
            $corrected = trim($json['choices'][0]['message']['content'] ?? $query);
            $this->log("Corrected: {$corrected}");
            return $corrected;
        } catch (\Exception $e) {
            $this->log("Exception: " . $e->getMessage());
            return $query;
        }
    }

    protected function getApiUrl(): string
    {
        if ($this->provider === 'custom' && !empty($this->baseUrl)) {
            return rtrim($this->baseUrl, '/') . '/v1/chat/completions';
        }
        // По умолчанию Mistral, можно добавить Groq
        if ($this->provider === 'groq') {
            return 'https://api.groq.com/openai/v1/chat/completions';
        }
        return 'https://api.mistral.ai/v1/chat/completions';
    }

    protected function log(string $message): void
    {
        $logFile = $_SERVER["DOCUMENT_ROOT"] . "/upload/mlk_llm_debug.log";
        file_put_contents($logFile, date("Y-m-d H:i:s") . " {$message}\n", FILE_APPEND);
    }
}
