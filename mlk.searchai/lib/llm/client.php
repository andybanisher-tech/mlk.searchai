<?php
namespace Mlk\Searchai\Llm;

use LucianoTonet\GroqPHP\Groq;
use Bitrix\Main\Config\Option;

class Client
{
    protected $client;
    protected $model = 'llama-3.3-70b-versatile';
    
    public function __construct()
    {
        $apiKey = Option::get('mlk.searchai', 'llm_api_key', '');
        $this->client = new Groq($apiKey);
    }
    
    public function correctQuery($query)
    {
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
            // При ошибке возвращаем оригинальный запрос
            return $query;
        }
    }
    
    protected function logRequest($query, $response)
    {
        // Сохраняем в b_searchai_llm_log
    }
}