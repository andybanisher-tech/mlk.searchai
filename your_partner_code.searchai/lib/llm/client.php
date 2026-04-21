<?php

namespace YourPartnerCode\SearchAi\LLM;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

class Client
{
    protected string $apiKey;
    protected string $apiEndpoint;
    protected string $model;
    protected HttpClient $httpClient;

    public function __construct()
    {
        $this->apiKey = Option::get('your_partner_code.searchai', 'llm_api_key', '');
        $this->apiEndpoint = Option::get('your_partner_code.searchai', 'llm_api_endpoint', 'https://api.openai.com/v1');
        $this->model = Option::get('your_partner_code.searchai', 'llm_model', 'gpt-3.5-turbo');
        $this->httpClient = new HttpClient();
    }

    public function complete(string $prompt, array $options = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'API key is not configured'
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful search assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500
        ];

        $this->httpClient->setHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ]);

        try {
            $response = $this->httpClient->post($this->apiEndpoint . '/chat/completions', Json::encode($payload));
            $data = Json::decode($response);

            if (isset($data['choices'][0]['message']['content'])) {
                $this->logRequest($prompt, $data['choices'][0]['message']['content'], $data['usage'] ?? []);
                
                return [
                    'success' => true,
                    'response' => $data['choices'][0]['message']['content'],
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0
                ];
            }

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Unknown error'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function logRequest(string $prompt, string $response, array $usage): void
    {
        $helper = Application::getConnection()->getSqlHelper();
        
        Application::getConnection()->queryExecute("
            INSERT INTO b_searchai_llm_logs (REQUEST_ID, PROMPT, RESPONSE, TOKENS_USED, DATE_CREATE)
            VALUES (:request_id, :prompt, :response, :tokens_used, NOW())
        ", [
            'request_id' => $helper->forSql(bin2hex(random_bytes(16))),
            'prompt' => $prompt,
            'response' => $response,
            'tokens_used' => $usage['total_tokens'] ?? 0
        ]);
    }

    public function getUsageStats(\DateTime $startDate, \DateTime $endDate): array
    {
        $helper = Application::getConnection()->getSqlHelper();
        
        $result = Application::getConnection()->queryExecute("
            SELECT 
                DATE(DATE_CREATE) as date,
                SUM(TOKENS_USED) as total_tokens,
                COUNT(*) as request_count
            FROM b_searchai_llm_logs
            WHERE DATE_CREATE BETWEEN :start AND :end
            GROUP BY DATE(DATE_CREATE)
            ORDER BY date DESC
        ", [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s')
        ]);

        $stats = [];
        while ($row = $result->fetch()) {
            $stats[] = $row;
        }

        return $stats;
    }
}
