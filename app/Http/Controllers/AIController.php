<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiClient;
use App\Services\QueryLookups;
use App\Services\QueryRouter;
use App\Services\WeeklySummaryBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    //

    public function weeklySummary(Request $request, WeeklySummaryBuilder $builder, AiClient $ai)
    {
        $request->validate([
            'start'   => 'nullable|date',
            'end'     => 'nullable|date|after_or_equal:start',
            'user_id' => 'nullable|integer',
        ]);

        $today = now();

        if ($request->filled('start') && $request->filled('end')) {
            $start = Carbon::parse($request->start)->startOfDay();
            $end   = Carbon::parse($request->end)->endOfDay();

            // Cap the range so the payload stays small.
            if ($start->diffInDays($end) > 31) {
                return response()->json(['message' => 'Please choose a range of 31 days or less.'], 422);
            }
            // Don't summarize the future.
            if ($end->gt($today)) {
                $end = $today->copy()->endOfDay();
            }
        } else {
            // Default: last full Mon-Sun week.
            $start = $today->copy()->subWeek()->startOfWeek();
            $end   = $start->copy()->endOfWeek();
        }

        $requester = $request->user();
        // Permission check happens in code, not in the prompt.
        $target = $request->filled('user_id') ? User::findOrFail($request->integer('user_id')) : $requester;
        if ($target->id !== $requester->id && !$requester->isManager()) {
            abort(403);
        }

        // Last full Mon-Sun week.
        $today = now();
        $start = $today->copy()->subWeek()->startOfWeek();
        $end   = $start->copy()->endOfWeek();

        $payload = $builder->build($target, $start, $end, $today);

        // Nothing to summarize: skip the API call entirely.
        if ($payload['status'] === 'no_activity') {
            return response()->json(['summary' => 'No recorded activity for this period.', 'period' => $payload['period']]);
        }

        try {
            $summary = $ai->complete(
                system: file_get_contents(resource_path('prompts/weekly_summary.txt')),
                userMessage: "DATA:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\nWrite the weekly summary.",
            );
        } catch (\Throwable $e) {
            Log::error('weeklySummary AI failed', ['user' => $target->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'The AI summary is unavailable right now.'], 503);
        }

        return response()->json(['summary' => $summary, 'period' => $payload['period']]);
    }

    // in AIController
    public function query(Request $request, QueryRouter $router, QueryLookups $lookups, AiClient $ai)
    {
        $data  = $request->validate(['question' => 'required|string|max:300']);
        $user  = $request->user();
        $today = today();

        try {
            $route = $router->classify($data['question']);

            if ($route['intent'] === 'unsupported') {
                return response()->json([
                    'answer' => "I can't answer that yet. I can help with your overdue tasks, tasks due this week, leave balance, project status, and who is on leave.",
                    'intent' => 'unsupported',
                ]);
            }

            $payload = $lookups->run($route['intent'], $user, $route['params'], $today);

            $answer = $ai->complete(
                file_get_contents(resource_path('prompts/query_answer.txt')),
                "DATA:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\nQUESTION:\n" . $data['question'],
            );
        } catch (\Throwable $e) {
            Log::error('AI query failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'The AI assistant is unavailable right now. Please try again.'], 503);
        }

        return response()->json([
            'answer'  => $answer,
            'intent'  => $route['intent'],
            'status'  => $payload['status'],
            // Lets the frontend render clickable buttons when the AI asks "which project?"
            'matches' => $payload['status'] === 'needs_clarification' ? $payload['result']['matches'] : [],
        ]);
    }
}
