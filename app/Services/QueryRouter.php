<?php

namespace App\Services;

class QueryRouter
{
    public const INTENTS = ['overdue_tasks', 'tasks_due_this_week', 'leave_balance', 'project_status', 'team_on_leave'];

    public function __construct(private AiClient $ai) {}

    public function classify(string $question): array
    {
        $raw = $this->ai->complete(
            file_get_contents(resource_path('prompts/query_router.txt')),
            "QUESTION:\n" . $question,
            maxTokens: 100,
            model: config('services.ai.model') ?: null,
        );

        // Some models wrap JSON in ```json fences.
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));
        $data = json_decode($raw, true);

        $intent = $data['intent'] ?? 'unsupported';
        if (!in_array($intent, self::INTENTS, true)) {
            return ['intent' => 'unsupported', 'params' => []];
        }

        return ['intent' => $intent, 'params' => is_array($data['params'] ?? null) ? $data['params'] : []];
    }
}