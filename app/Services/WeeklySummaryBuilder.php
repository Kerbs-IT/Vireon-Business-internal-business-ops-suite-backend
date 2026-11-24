<?php

namespace App\Services;

use App\Models\{Leave_requests, User, Task, Tasks};
use App\Services\LeaveBalance;
use Carbon\{Carbon, CarbonPeriod};

class WeeklySummaryBuilder
{
    public function build(User $user, Carbon $start, Carbon $end, Carbon $today): array
    {
        $completed = Tasks::with('project:id,name')
            ->where('assigned_to', $user->id)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $openQuery = Tasks::with('project:id,name')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', 'done')
            ->orderBy('due_date');
        $openTotal = (clone $openQuery)->count();
        $open = $openQuery->limit(10)->get();

        $leave = Leave_requests::with('leave_type:id,name')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        $leaveRows = $leave->map(fn($l) => [
            'leave_type' => $l->leave_type->name,
            'start_date' => $l->start_date->toDateString(),
            'end_date' => $l->end_date->toDateString(),
            'working_days' => $this->workingDays($l->start_date, $l->end_date, $start, $end),
        ])->values();

        $tasksCompleted = $completed->map(fn($t) => [
            'title' => $t->title,
            'project' => $t->project->name,
            'due_date' => $t->due_date?->toDateString(),
            'completed_on' => $t->completed_at->toDateString(),
            'days_late' => $t->due_date ? max(0, $t->due_date->diffInDays($t->completed_at, false)) : 0,
        ])->values();

        $tasksOpen = $open->map(fn($t) => [
            'title' => $t->title,
            'project' => $t->project->name,
            'status' => $t->status,
            'due_date' => $t->due_date?->toDateString(),
            'due_weekday' => $t->due_date?->format('l'),
            'days_overdue' => $t->due_date && $t->due_date->lt($today) ? $t->due_date->diffInDays($today) : 0,
        ])->values();

        $noActivity = $tasksCompleted->isEmpty() && $tasksOpen->isEmpty() && $leaveRows->isEmpty();

        return [
            'intent' => 'weekly_summary',
            'status' => $noActivity ? 'no_activity' : 'ok',
            'today' => $today->toDateString(),
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'employee' => ['name' => "{$user->first_name} {$user->last_name}", 'position' => $user->position],
            'counts' => [
                'completed' => $tasksCompleted->count(),
                'completed_late' => $tasksCompleted->where('days_late', '>', 0)->count(),
                'open' => $openTotal,
                'overdue' => $tasksOpen->where('days_overdue', '>', 0)->count(),
            ],
            'completed_by_project' => $tasksCompleted->groupBy('project')
                ->map(fn($g, $name) => ['project' => $name, 'count' => $g->count()])
                ->sortByDesc('count')->values(),
            'tasks_completed' => $tasksCompleted,
            'tasks_open' => $tasksOpen,
            'tasks_open_truncated' => $openTotal > 10,
            'leave' => [
                'days_taken_in_period' => $leaveRows->sum('working_days'),
                'requests_in_period' => $leaveRows,
                'balance' => app(LeaveBalance::class)->forMonth($user, $today), // reuse your leave_balance logic
            ],
        ];
    }

    /** Weekdays of the request that fall inside the period. */
    private function workingDays(Carbon $from, Carbon $to, Carbon $pStart, Carbon $pEnd): int
    {
        $s = $from->max($pStart);
        $e = $to->min($pEnd);
        return collect(CarbonPeriod::create($s, $e))->filter(fn($d) => $d->isWeekday())->count();
    }
}
