<?php

// app/Services/QueryLookups.php
namespace App\Services;
use App\Models\{User, Tasks, Project, Leave_requests, Projects};
use Illuminate\Support\Arr;
use Carbon\Carbon;

class QueryLookups
{
    private const LIMIT = 15;

    public function run(string $intent, User $user, array $params, Carbon $today): array
    {
        return match ($intent) {
            'overdue_tasks'           => $this->overdueTasks($user, $today),
            'tasks_due_this_week'     => $this->dueThisWeek($user, $params, $today),
            'leave_balance'           => $this->leaveBalance($user, $today),
            'project_status'          => $this->projectStatus($user, $params, $today),
            'team_on_leave'           => $this->teamOnLeave($user, $params, $today),
            'project_tasks'           => $this->projectTasks($user, $params, $today),
            'project_task_counts'     => $this->projectTaskCounts($user, $params, $today),
            // employee
            'my_leave_requests'       => $this->myLeaveRequests($user, $params, $today),
            'project_members'         => $this->projectMembers($user, $params, $today),
            'my_completed_tasks'      => $this->myCompletedTasks($user, $params, $today),
            'my_task_summary'         => $this->myTaskSummary($user, $today),
            'leave_check'             => $this->leaveCheck($user, $params, $today),
            // manager
            'pending_leave_approvals' => $this->pendingLeaveApprovals($user, $today),
            'employees_with_overdue'  => $this->tasksByAssignee('employees_with_overdue', $user, $params, $today, true),
            'team_workload'           => $this->tasksByAssignee('team_workload', $user, $params, $today, false),
            'team_availability'       => $this->teamAvailability($user, $params, $today),
            'project_overdue_ranking' => $this->projectOverdueRanking($user, $today),
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

    private function dueThisWeek(User $user, array $params, Carbon $today): array
    {
        $range = in_array($params['range'] ?? '', ['today', 'tomorrow', 'this_week', 'next_week'], true)
            ? $params['range'] : 'this_week';

        [$start, $end] = match ($range) {
            'today'     => [$today->copy(), $today->copy()],
            'tomorrow'  => [$today->copy()->addDay(), $today->copy()->addDay()],
            'next_week' => [$today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek()],
            default     => [$today->copy(), $today->copy()->endOfWeek()],
        };

        $q = Tasks::with('project:id,name')->where('assigned_to', $user->id)
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('due_date');

        return $this->taskList(
            'tasks_due_this_week',
            $user,
            $today,
            ['range' => $range, 'range_start' => $start->toDateString(), 'range_end' => $end->toDateString()],
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
    private function resolveProject(User $user, string $name): array
    {
        $matches = Projects::where('name', 'like', "%{$name}%")
            ->when(!$user->isManager(), fn($q) => $q->whereHas('members', fn($m) => $m->where('user_id', $user->id)))
            ->limit(6)->get();

        if ($name === '' || $matches->isEmpty()) return ['status' => 'no_match', 'matches' => []];
        if ($matches->count() > 1) return ['status' => 'needs_clarification', 'matches' => $matches->pluck('name')->all()];
        return ['status' => 'ok', 'project' => $matches->first()];
    }
    private function projectTasks(User $user, array $params, Carbon $today): array
    {
        $isManager = $user->isManager();

        // Whitelist the AI's parameter. Anything unknown falls back to a safe default.
        $filter = $params['status_filter'] ?? 'all';
        $priority = $params['priority'] ?? null;
        if (!in_array($priority, ['high', 'medium', 'low'], true)) $priority = null;
        if (!in_array($filter, ['overdue', 'not_done', 'all', 'todo', 'in_progress', 'review', 'done'], true)) {
            $filter = 'all';
        }

        $name = trim($params['project_name'] ?? '');
        $p = [
            'project_name' => $name,
            'status_filter' => $filter,
            'scope' => $isManager ? 'all_assignees' : 'own_tasks_only'
        ];

        $query = Tasks::with(['project:id,name', 'assignee:id,first_name,last_name'])->orderBy('due_date');


        if ($priority) $query->where('priority', $priority);

        if ($name !== '') {
            $found = $this->resolveProject($user, $name);
            if ($found['status'] !== 'ok') {
                return $this->envelope(
                    'project_tasks',
                    $user,
                    $today,
                    $p,
                    ['reason' => $found['status'], 'matches' => $found['matches']],
                    $found['status'],
                    count($found['matches'])
                );
            }
            $query->where('project_id', $found['project']->id);
            $p['project_name'] = $found['project']->name;
        } elseif ($isManager) {
            // A manager asking across all projects would be huge. Ask which project instead.
            return $this->envelope(
                'project_tasks',
                $user,
                $today,
                $p,
                ['reason' => 'project_name_required', 'matches' => []],
                'needs_clarification',
                0
            );
        }

        // Scope is decided here, never by the AI.
        if (!$isManager) $query->where('assigned_to', $user->id);

        if ($filter === 'overdue')      $query->where('status', '!=', 'done')->whereDate('due_date', '<', $today);
        elseif ($filter === 'not_done') $query->where('status', '!=', 'done');
        elseif ($filter !== 'all')      $query->where('status', $filter);

        $total = (clone $query)->count();
        $rows = $query->limit(20)->get()->map(fn($t) => [
            'title'        => $t->title,
            'project'      => $t->project->name,
            'assignee'     => $isManager ? ($t->assignee ? "{$t->assignee->first_name} {$t->assignee->last_name}" : 'Unassigned') : null,
            'status'       => $t->status,
            'due_date'     => $t->due_date?->toDateString(),
            'due_weekday'  => $t->due_date?->format('l'),
            'days_overdue' => $t->due_date && $t->status !== 'done' && $t->due_date->lt($today)
                ? (int) $t->due_date->copy()->startOfDay()->diffInDays($today->copy()->startOfDay()) : 0,
            'priority' => $t->priority,
        ])->values();

        return $this->envelope('project_tasks', $user, $today, $p, $rows, 'ok', $total, $total > 20);
    }

    private function projectTaskCounts(User $user, array $params, Carbon $today): array
    {
        $name = trim($params['project_name'] ?? '');
        $p = ['project_name' => $name];
        $found = $this->resolveProject($user, $name);

        if ($found['status'] !== 'ok') {
            return $this->envelope(
                'project_task_counts',
                $user,
                $today,
                $p,
                ['reason' => $found['status'], 'matches' => $found['matches']],
                $found['status'],
                count($found['matches'])
            );
        }

        $project = $found['project'];
        $c = $project->tasks()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return $this->envelope('project_task_counts', $user, $today, ['project_name' => $project->name], [
            'project' => $project->name,
            'todo'        => (int) ($c['todo'] ?? 0),
            'in_progress' => (int) ($c['in_progress'] ?? 0),
            'review'      => (int) ($c['review'] ?? 0),
            'done'        => (int) ($c['done'] ?? 0),
            'total'       => (int) $c->sum(),
            'overdue'     => $project->tasks()->where('status', '!=', 'done')->whereDate('due_date', '<', $today)->count(),
        ], 'ok', 1);
    }

    private function myLeaveRequests(User $user, array $params, Carbon $today): array
    {
        $filter = in_array($params['status_filter'] ?? '', ['pending', 'approved', 'rejected', 'all'], true)
            ? $params['status_filter'] : 'all';

        $q = Leave_requests::with('leave_type:id,name')
            ->where('user_id', $user->id)
            ->whereDate('end_date', '>=', $today->copy()->subDays(30))   // last 30 days and upcoming
            ->when($filter !== 'all', fn($q) => $q->where('status', $filter))
            ->orderByDesc('start_date');

        $total = (clone $q)->count();
        $rows = $q->limit(self::LIMIT)->get()->map(fn($l) => [
            'leave_type'   => $l->leave_type->name,
            'status'       => $l->status,
            'start_date'   => $l->start_date->toDateString(),
            'end_date'     => $l->end_date->toDateString(),
            'working_days' => LeaveBalance::workingDays($l->start_date, $l->end_date, $l->start_date, $l->end_date),
        ])->values();

        return $this->envelope(
            'my_leave_requests',
            $user,
            $today,
            ['status_filter' => $filter, 'window' => 'last_30_days_and_upcoming'],
            $rows,
            'ok',
            $total,
            $total > self::LIMIT
        );
    }

    private function projectMembers(User $user, array $params, Carbon $today): array
    {
        $name = trim($params['project_name'] ?? '');
        $p = ['project_name' => $name];
        $found = $this->resolveProject($user, $name);
        if ($found['status'] !== 'ok') return $this->clarify('project_members', $user, $today, $p, $found);

        $project = $found['project'];
        $total = $project->members()->count();
        $rows = $project->members()->limit(30)->get()
            ->map(fn($u) => ['name' => $this->fullName($u), 'position' => $u->position])->values();

        return $this->envelope(
            'project_members',
            $user,
            $today,
            ['project_name' => $project->name],
            ['project' => $project->name, 'members' => $rows],
            'ok',
            $total,
            $total > 30
        );
    }

    private function myCompletedTasks(User $user, array $params, Carbon $today): array
    {
        $range = in_array($params['range'] ?? '', ['this_week', 'last_week', 'this_month'], true)
            ? $params['range'] : 'this_week';

        [$start, $end] = match ($range) {
            'last_week'  => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            default      => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
        };

        $q = Tasks::with('project:id,name')->where('assigned_to', $user->id)->where('status', 'done')
            ->whereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderByDesc('completed_at');

        $total = (clone $q)->count();
        $rows = $q->limit(self::LIMIT)->get()->map(fn($t) => [
            'title'        => $t->title,
            'project'      => $t->project->name,
            'due_date'     => $t->due_date?->toDateString(),
            'completed_on' => $t->completed_at->toDateString(),
            'days_late'    => $t->due_date
                ? max(0, (int) $t->due_date->copy()->startOfDay()->diffInDays($t->completed_at->copy()->startOfDay(), false))
                : 0,
        ])->values();

        return $this->envelope(
            'my_completed_tasks',
            $user,
            $today,
            ['range' => $range, 'range_start' => $start->toDateString(), 'range_end' => $end->toDateString()],
            $rows,
            'ok',
            $total,
            $total > self::LIMIT
        );
    }

    private function myTaskSummary(User $user, Carbon $today): array
    {
        $base = Tasks::where('assigned_to', $user->id);
        $c = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $open = (clone $base)->where('status', '!=', 'done');

        return $this->envelope('my_task_summary', $user, $today, [], [
            'todo'          => (int) ($c['todo'] ?? 0),
            'in_progress'   => (int) ($c['in_progress'] ?? 0),
            'review'        => (int) ($c['review'] ?? 0),
            'done_total'    => (int) ($c['done'] ?? 0),
            'open_total'    => (clone $open)->count(),
            'overdue'       => (clone $open)->whereDate('due_date', '<', $today)->count(),
            'due_this_week' => (clone $open)->whereBetween(
                'due_date',
                [$today->toDateString(), $today->copy()->endOfWeek()->toDateString()]
            )->count(),
        ], 'ok', 1);
    }

    private function leaveCheck(User $user, array $params, Carbon $today): array
    {
        $start = $this->parseDate($params['start_date'] ?? null);
        $end   = $this->parseDate($params['end_date'] ?? null) ?? $start;
        $p = ['start_date' => $params['start_date'] ?? null, 'end_date' => $params['end_date'] ?? null];

        if (!$start || !$end || $end->lt($start)) {
            return $this->envelope(
                'leave_check',
                $user,
                $today,
                $p,
                ['reason' => 'invalid_date', 'matches' => []],
                'needs_clarification',
                0
            );
        }
        if ($start->lt($today)) {
            return $this->envelope(
                'leave_check',
                $user,
                $today,
                $p,
                ['reason' => 'date_in_past', 'matches' => []],
                'needs_clarification',
                0
            );
        }

        $workingDays = LeaveBalance::workingDays($start, $end, $start, $end);
        $balance = Arr::only(
            app(LeaveBalance::class)->forMonth($user, $start),
            ['period', 'allowance', 'used', 'remaining', 'pending_days']
        );

        $mates = Leave_requests::with('user:id,first_name,last_name')
            ->where('status', 'approved')->where('user_id', '!=', $user->id)
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->whereHas('user', fn($u) => $u->where('department_id', $user->department_id))
            ->orderBy('start_date');

        $total = (clone $mates)->count();
        $rows = $mates->limit(10)->get()->map(fn($l) => [
            'name'       => $this->fullName($l->user),
            'start_date' => $l->start_date->toDateString(),
            'end_date'   => $l->end_date->toDateString(),
        ])->values();

        return $this->envelope('leave_check', $user, $today, $p, [
            'start_date'               => $start->toDateString(),
            'start_weekday'            => $start->format('l'),
            'end_date'                 => $end->toDateString(),
            'end_weekday'              => $end->format('l'),
            'requested_working_days'   => $workingDays,
            'balance'                  => $balance,
            'enough_balance'           => $workingDays <= $balance['remaining'],
            'spans_multiple_months'    => !$start->isSameMonth($end),
            'teammates_on_leave'       => $rows,
            'teammates_on_leave_count' => $total,
        ], 'ok', 1);
    }

    private function pendingLeaveApprovals(User $user, Carbon $today): array
    {
        if ($deny = $this->managerOnly('pending_leave_approvals', $user, $today, [])) return $deny;

        $q = Leave_requests::with(['user:id,first_name,last_name,position', 'leave_type:id,name'])
            ->where('status', 'pending')->where('user_id', '!=', $user->id)   // can't approve your own
            ->whereDate('end_date', '>=', $today)->orderBy('start_date');

        $total = (clone $q)->count();
        $rows = $q->limit(self::LIMIT)->get()->map(fn($l) => [
            'name'         => $this->fullName($l->user),
            'position'     => $l->user?->position,
            'leave_type'   => $l->leave_type->name,
            'start_date'   => $l->start_date->toDateString(),
            'end_date'     => $l->end_date->toDateString(),
            'working_days' => LeaveBalance::workingDays($l->start_date, $l->end_date, $l->start_date, $l->end_date),
            'requested_on' => $l->created_at?->toDateString(),
        ])->values();

        return $this->envelope('pending_leave_approvals', $user, $today, [], $rows, 'ok', $total, $total > self::LIMIT);
    }

    // Shared by employees_with_overdue ($overdueOnly = true) and team_workload (false).
    private function tasksByAssignee(string $intent, User $user, array $params, Carbon $today, bool $overdueOnly): array
    {
        if ($deny = $this->managerOnly($intent, $user, $today, $params)) return $deny;

        $name = trim($params['project_name'] ?? '');
        $p = ['project_name' => $name ?: null];

        $q = Tasks::where('status', '!=', 'done')->whereNotNull('assigned_to');

        if ($name !== '') {
            $found = $this->resolveProject($user, $name);
            if ($found['status'] !== 'ok') return $this->clarify($intent, $user, $today, $p, $found);
            $q->where('project_id', $found['project']->id);
            $p['project_name'] = $found['project']->name;
        }
        if ($overdueOnly) $q->whereDate('due_date', '<', $today);

        $totalPeople = (clone $q)->distinct()->count('assigned_to');

        $groups = $q->selectRaw(
            'assigned_to, COUNT(*) as open_tasks,
             SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_tasks,
             MIN(due_date) as oldest_due',
            [$today->toDateString()]
        )
            ->groupBy('assigned_to')
            ->orderByDesc($overdueOnly ? 'overdue_tasks' : 'open_tasks')
            ->limit(self::LIMIT)->get();

        $people = User::whereIn('id', $groups->pluck('assigned_to'))
            ->get(['id', 'first_name', 'last_name', 'position'])->keyBy('id');

        $rows = $groups->map(fn($g) => [
            'name'                 => $this->fullName($people[$g->assigned_to] ?? null),
            'position'             => $people[$g->assigned_to]->position ?? null,
            'open_tasks'           => (int) $g->open_tasks,
            'overdue_tasks'        => (int) $g->overdue_tasks,
            'oldest_overdue_days'  => $this->daysOverdue($g->oldest_due ? Carbon::parse($g->oldest_due) : null, $today),
        ])->values();

        return $this->envelope($intent, $user, $today, $p, $rows, 'ok', $totalPeople, $totalPeople > self::LIMIT);
    }

    private function teamAvailability(User $user, array $params, Carbon $today): array
    {
        if ($deny = $this->managerOnly('team_availability', $user, $today, $params)) return $deny;

        $range = ($params['range'] ?? '') === 'this_week' ? 'this_week' : 'next_week';
        [$start, $end] = $range === 'this_week'
            ? [$today->copy(), $today->copy()->endOfWeek()]
            : [$today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek()];

        // The manager's department. Remove the ->when(...) line to cover the whole company.
        $people = User::when($user->department_id, fn($q) => $q->where('department_id', $user->department_id))
            ->get(['id', 'first_name', 'last_name', 'position']);

        $leaves = Leave_requests::where('status', 'approved')->whereIn('user_id', $people->pluck('id'))
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get();

        $periodDays = LeaveBalance::workingDays($start, $end, $start, $end);
        $out = [];   // user_id => working days out during the period
        foreach ($leaves as $l) {
            $d = LeaveBalance::workingDays($l->start_date, $l->end_date, $start, $end);
            if ($d > 0) $out[$l->user_id] = min($periodDays, ($out[$l->user_id] ?? 0) + $d);
        }

        $onLeave = $people->filter(fn($u) => isset($out[$u->id]))->map(fn($u) => [
            'name' => $this->fullName($u),
            'position' => $u->position,
            'working_days_out' => $out[$u->id],
        ])->values();

        $available = $people->reject(fn($u) => isset($out[$u->id]));

        return $this->envelope('team_availability', $user, $today, [
            'range' => $range,
            'range_start' => $start->toDateString(),
            'range_end' => $end->toDateString(),
            'scope' => $user->department_id ? 'department' : 'company',
        ], [
            'period_working_days'  => $periodDays,
            'on_leave'             => $onLeave->take(20)->values(),
            'on_leave_count'       => $onLeave->count(),
            'available'            => $available->take(20)->map(fn($u) => [
                'name' => $this->fullName($u),
                'position' => $u->position
            ])->values(),
            'available_count'      => $available->count(),
            'available_truncated'  => $available->count() > 20,
        ], 'ok', $available->count());
    }

    private function projectOverdueRanking(User $user, Carbon $today): array
    {
        if ($deny = $this->managerOnly('project_overdue_ranking', $user, $today, [])) return $deny;

        $groups = Tasks::where('status', '!=', 'done')->whereDate('due_date', '<', $today)
            ->selectRaw('project_id, COUNT(*) as overdue_tasks')
            ->groupBy('project_id')->orderByDesc('overdue_tasks')->limit(10)->get();

        $ids = $groups->pluck('project_id');
        $projects = Projects::whereIn('id', $ids)->get(['id', 'name', 'status'])->keyBy('id');
        $totals = Tasks::whereIn('project_id', $ids)->selectRaw('project_id, COUNT(*) as total')
            ->groupBy('project_id')->pluck('total', 'project_id');

        $rows = $groups->map(fn($g) => [
            'project'       => $projects[$g->project_id]->name ?? 'Unknown',
            'status'        => $projects[$g->project_id]->status ?? null,
            'overdue_tasks' => (int) $g->overdue_tasks,
            'total_tasks'   => (int) ($totals[$g->project_id] ?? 0),
        ])->values();

        return $this->envelope('project_overdue_ranking', $user, $today, [], $rows);
    }

    private function daysOverdue(?Carbon $due, Carbon $today): int
    {
        if (!$due || !$due->lt($today)) return 0;
        return (int) $due->copy()->startOfDay()->diffInDays($today->copy()->startOfDay());
    }

    private function fullName($u): string
    {
        return $u ? trim("{$u->first_name} {$u->last_name}") : 'Unassigned';
    }

    private function parseDate(?string $s): ?Carbon
    {
        if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
        try {
            return Carbon::createFromFormat('Y-m-d', $s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    // Returns a "not_allowed" payload for non-managers, or null if allowed.
    private function managerOnly(string $intent, User $user, Carbon $today, array $params): ?array
    {
        return $user->isManager() ? null
            : $this->envelope($intent, $user, $today, $params, ['reason' => 'manager_only'], 'not_allowed', 0);
    }

    private function clarify(string $intent, User $user, Carbon $today, array $p, array $found): array
    {
        return $this->envelope(
            $intent,
            $user,
            $today,
            $p,
            ['reason' => $found['status'], 'matches' => $found['matches']],
            $found['status'],
            count($found['matches'])
        );
    }
}