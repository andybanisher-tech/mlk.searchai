<?php

namespace Mlk\Searchai\Llm;

use LucianoTonet\GroqPHP\Groq;
use Bitrix\Main\Config\Option;

class Client
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $moduleId = 'mlk.searchai';
        $provider = Option::get($moduleId, 'llm_provider', 'groq');
        $apiKey = Option::get($moduleId, 'llm_api_key', '');
        $this->model = Option::get($moduleId, 'llm_model', 'llama-3.3-70b-versatile');

        if ($provider === 'groq' && !empty($apiKey)) {
            $this->client = new Groq($apiKey);
        }
        // В будущем сюда можно добавить других провайдеров
    }

    public function correctQuery($query)
    {
        // Если клиент не инициализирован (нет ключа), сразу возвращаем оригинал
        if (!$this->client) {
            return $query;
        }

        $prompt = "Исправь опечатки и транслитерацию в поисковом запросе пользователя. Ответь только исправленным текстом без пояснений.\nЗапрос: '{$query}'\nИсправленный запрос:";

        try {
            $response = $this->client->chat()->completions()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'max_tokens' => 100
            ]);

            $correctedQuery = trim($response['choices'][0]['message']['content']);
            $this->logRequest($query, $correctedQuery);
            return $correctedQuery;
        } catch (\Exception $e) {
            // При любой ошибке (сеть, лимит, и т.д.) возвращаем оригинальный запрос
            return $query;
        }
    }

    protected function logRequest($query, $response)
    {
        // Здесь будет сохранение в b_searchai_llm_log, которое мы добавим на следующем шаге
    }
}
