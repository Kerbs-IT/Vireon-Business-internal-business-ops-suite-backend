<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiClient
{
    public function complete(string $system, string $userMessage, int $maxTokens = 1000, ?string $model = null): string
    {
        $body = [
            'model'      => $model ?? config('services.ai.model'),
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userMessage],
            ],
        ];

        if (config('services.ai.reasoning_effort')) {
            $body['reasoning_effort'] = config('services.ai.reasoning_effort');
        }

        $response = Http::withToken(config('services.ai.key'))
            ->timeout(config('services.ai.timeout'))
            ->retry(3, 2000, fn($e) => $e instanceof \Illuminate\Http\Client\RequestException
                && in_array($e->response->status(), [429, 500, 503]))
            ->post(rtrim(config('services.ai.base_url'), '/') . '/chat/completions', $body)
            ->throw();

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
