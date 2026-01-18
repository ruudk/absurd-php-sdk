<?php declare(strict_types=1);

namespace Ruudk\Absurd\Examples\Agent;

use RuntimeException;

/**
 * Simple OpenAI API client for chat completions with tool support.
 */
final class OpenAIClient
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

    /**
     * Send a chat completion request with optional tools.
     *
     * @param list<array{role: string, content?: string, tool_calls?: array, tool_call_id?: string, name?: string}> $messages
     * @param list<array{type: string, function: array{name: string, description: string, parameters: array}}> $tools
     * @return array{message: array, finish_reason: string}
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->request($payload);

        if (!isset($response['choices'][0])) {
            throw new RuntimeException('Invalid OpenAI response: no choices');
        }

        $choice = $response['choices'][0];

        return [
            'message' => $choice['message'],
            'finish_reason' => $choice['finish_reason'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($response === false) {
            throw new RuntimeException('cURL error: ' . $error);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if ($httpCode !== 200) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("OpenAI API error ({$httpCode}): {$errorMessage}");
        }

        return $data;
    }
}
