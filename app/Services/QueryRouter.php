<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QueryRouter
{
    public const INTENTS = [
        'overdue_tasks',
        'tasks_due_this_week',
        'leave_balance',
        'project_status',
        'team_on_leave',
        'project_tasks',
        'project_task_counts',
        'my_leave_requests',
        'project_members',
        'my_completed_tasks',
        'my_task_summary',
        'leave_check',
        'pending_leave_approvals',
        'employees_with_overdue',
        'team_workload',
        'team_availability',
        'project_overdue_ranking',
        'weekly_summary',
        'assistant_info'
    ];

    public function __construct(private AiClient $ai) {}

    public function classify(string $question, array $history = []): array
    {
        $context = $history
            ? "PREVIOUS QUESTIONS (oldest first):\n- " . implode("\n- ", $history) . "\n\n"
            : '';

        $context = "TODAY: " . today()->format('Y-m-d (l)') . "\n\n" . $context;

        $raw = $this->ai->complete(
            file_get_contents(resource_path('prompts/query_router.txt')),
            $context . "QUESTION:\n" . $question,
            maxTokens: 1000,
            model: config('services.ai.ai_model') ?: null,
        );

        // Some models wrap JSON in ```json fences.
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));
        // Log::info('ROUTER RAW', ['question' => $question, 'raw' => $raw]);
        $data = json_decode($raw, true);

        $intent = $data['intent'] ?? 'unsupported';
        if (!in_array($intent, self::INTENTS, true)) {
            return ['intent' => 'unsupported', 'params' => []];
        }

        return ['intent' => $intent, 'params' => is_array($data['params'] ?? null) ? $data['params'] : []];
    }
}