<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MistralService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $mistralApiKey
    ) {}

    public function generate(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->mistralApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'    => 'mistral-small-latest',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 500,
            ],
        ]);

        $data = $response->toArray();
        return $data['choices'][0]['message']['content'] ?? 'Erreur de génération.';
    }
}