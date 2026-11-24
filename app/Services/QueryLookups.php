<?php

// app/Services/QueryLookups.php
namespace App\Services;

use App\Models\Leave_requests;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use Carbon\Carbon;

class QueryLookups
{
    private const LIMIT = 15;

    public function run(string $intent, User $user, array $params, Carbon $today): array
    {
        return match ($intent) {
            'overdue_tasks'       => $this->overdueTasks($user, $today),
            'tasks_due_this_week' => $this->dueThisWeek($user, $today),
            'leave_balance'       => $this->leaveBalance($user, $today),
            'project_status'      => $this->projectStatus($user, $params, $today),
            'team_on_leave'       => $this->teamOnLeave($user, $params, $today),
        };
    }

    private function envelope(string $intent, User $user, Carbon $today, array $params, $result, string $status = 'ok', ?int $count = null, bool $truncated = false): array
    {
        return [
            'intent'    => $intent,
            'status'    => $status,
            'today'     => $today->toDateString(),
            'requester' => ['name' => "{$user->first_name} {$user->last_name}", 'role' => $user->isManager() ? 'manager' : 'employee'],
            'params'    => $params,
            'count'     => $count ?? count($result),
            'truncated' => $truncated,
            'result'    => $result,
        ];
    }

    private function taskRow(Tasks $t, Carbon $today): array
    {
        return [
            'title'        => $t->title,
            'project'      => $t->project->name,
            'status'       => $t->status,
            'due_date'     => $t->due_date?->toDateString(),
            'due_weekday'  => $t->due_date?->format('l'),
            'days_overdue' => $t->due_date && $t->due_date->lt($today) ? $t->due_date->diffInDays($today) : 0,
        ];
    }

    private function taskList(string $intent, User $user, Carbon $today, array $params, $query): array
    {
        $total = (clone $query)->count();
        $rows  = $query->limit(self::LIMIT)->get()->map(fn($t) => $this->taskRow($t, $today))->values();
        return $this->envelope($intent, $user, $today, $params, $rows, 'ok', $total, $total > self::LIMIT);
    }

    private function overdueTasks(User $user, Carbon $today): array
    {
        $q = Tasks::with('project:id,name')->where('assigned_to', $user->id)
            ->where('status', '!=', 'done')->whereDate('due_date', '<', $today)->orderBy('due_date');
        return $this->taskList('overdue_tasks', $user, $today, [], $q);
    }

    private function dueThisWeek(User $user, Carbon $today): array
    {
        $end = $today->copy()->endOfWeek();
        $q = Tasks::with('project:id,name')->where('assigned_to', $user->id)
            ->where('status', '!=', 'done')->whereBetween('due_date', [$today->toDateString(), $end->toDateString()])->orderBy('due_date');
        return $this->taskList(
            'tasks_due_this_week',
            $user,
            $today,
            ['week_start' => $today->toDateString(), 'week_end' => $end->toDateString()],
            $q
        );
    }

    private function leaveBalance(User $user, Carbon $today): array
    {
        $b = app(LeaveBalance::class)->forMonth($user, $today);
        return $this->envelope('leave_balance', $user, $today, ['period' => $b['period'], 'period_type' => 'monthly'], $b, 'ok', 1);
    }

    private function teamOnLeave(User $user, array $params, Carbon $today): array
    {
        [$start, $end] = match ($params['range'] ?? 'this_week') {
            'today'     => [$today->copy(), $today->copy()],
            'next_week' => [$today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek()],
            default     => [$today->copy(), $today->copy()->endOfWeek()],
        };

        // Scope is decided here, never by the AI.
        $scope = $user->isManager() ? 'company' : 'department';

        $rows = Leave_requests::with('user:id,first_name,last_name,position,department_id')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->when($scope === 'department', fn($q) => $q->whereHas('user', fn($u) => $u->where('department_id', $user->department_id)))
            ->orderBy('start_date')->limit(self::LIMIT)->get()
            ->map(fn($l) => [
                'name'          => "{$l->user->first_name} {$l->user->last_name}",
                'position'      => $l->user->position,
                'start_date'    => $l->start_date->toDateString(),
                'end_date'      => $l->end_date->toDateString(),
                'working_days'  => LeaveBalance::workingDays($l->start_date, $l->end_date, $start, $end),
                'on_leave_today' => $l->start_date->lte($today) && $l->end_date->gte($today),
            ])->values();

        return $this->envelope(
            'team_on_leave',
            $user,
            $today,
            ['range_start' => $start->toDateString(), 'range_end' => $end->toDateString(), 'scope' => $scope],
            $rows
        );
    }

    private function projectStatus(User $user, array $params, Carbon $today): array
    {
        $name = trim($params['project_name'] ?? '');
        $p = ['project_name' => $name];

        $matches = Projects::where('name', 'like', "%{$name}%")
            // Employees see only projects they belong to. Adjust to your members relation.
            ->when(!$user->isManager(), fn($q) => $q->whereHas('members', fn($m) => $m->where('user_id', $user->id)))
            ->limit(6)->get();

        if ($name === '' || $matches->isEmpty()) {
            return $this->envelope('project_status', $user, $today, $p, ['matches' => []], 'no_match', 0);
        }
        if ($matches->count() > 1) {
            return $this->envelope(
                'project_status',
                $user,
                $today,
                $p,
                ['reason' => 'multiple_projects_matched', 'matches' => $matches->pluck('name')->all()],
                'needs_clarification',
                $matches->count()
            );
        }

        $project = $matches->first();
        $total     = $project->tasks()->count();
        $completed = $project->tasks()->where('status', 'done')->count();
        $overdue   = $project->tasks()->where('status', '!=', 'done')->whereDate('due_date', '<', $today)->count();
        $openQ     = $project->tasks()->with('assignee:id,first_name,last_name')->where('status', '!=', 'done')->orderBy('due_date');
        $openTotal = $total - $completed;

        $open = $openQ->limit(10)->get()->map(fn($t) => [
            'title'        => $t->title,
            'assignee'     => $t->assignee ? "{$t->assignee->first_name} {$t->assignee->last_name}" : 'Unassigned',
            'status'       => $t->status,
            'due_date'     => $t->due_date?->toDateString(),
            'due_weekday'  => $t->due_date?->format('l'),
            'days_overdue' => $t->due_date && $t->due_date->lt($today) ? $t->due_date->diffInDays($today) : 0,
        ])->values();

        return $this->envelope('project_status', $user, $today, $p, [
            'name' => $project->name,
            'status' => $project->status,
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'open_tasks_count' => $openTotal,
            'overdue_tasks' => $overdue,
            'percent_complete' => $total ? (int) round($completed / $total * 100) : 0,
            'open_tasks' => $open,
            'open_tasks_shown' => $open->count(),
            'open_tasks_truncated' => $openTotal > 10,
        ], 'ok', 1);
    }
}