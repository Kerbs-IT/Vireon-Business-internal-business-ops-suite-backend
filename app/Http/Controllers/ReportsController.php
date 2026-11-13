<?php

namespace App\Http\Controllers;

use App\Models\Leave_requests;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    /* ------------------------------------------------------------------
     | Shared helpers
     ------------------------------------------------------------------ */

    /** "30d" -> 30, whitelisted. Returns [days, from, to]. */
    private function period(Request $request): array
    {
        $days = (int) rtrim($request->query('days', '30d'), 'd');
        $days = in_array($days, [7, 30, 90]) ? $days : 30;

        return [
            $days,
            now()->subDays($days - 1)->startOfDay(),
            now()->endOfDay(),
        ];
    }

    /** Returns [projectId|null, departmentId|null]. "All" is simply not sent by the frontend. */
    private function filters(Request $request): array
    {
        return [
            $request->filled('project_id') ? (int) $request->query('project_id') : null,
            $request->filled('department_id') ? (int) $request->query('department_id') : null,
        ];
    }

    /** Project applies directly; department goes through the assignee. */
    private function scopeTasks($query, ?int $projectId, ?int $departmentId)
    {
        return $query
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->when($departmentId, fn($q) => $q->whereHas(
                'assignee',
                fn($u) => $u->where('department_id', $departmentId)
            ));
    }

    /** Leave has no project, only a department through the user. */
    private function scopeLeave($query, ?int $departmentId)
    {
        return $query->when($departmentId, fn($q) => $q->whereHas(
            'user',
            fn($u) => $u->where('department_id', $departmentId)
        ));
    }

    /** Approved leave days that fall inside [from, to]. */
    private function leaveDaysBetween(Carbon $from, Carbon $to, ?int $departmentId): int
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay   = $to->copy()->startOfDay();

        return (int) $this->scopeLeave(Leave_requests::query(), $departmentId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get()
            ->sum(function ($leave) use ($fromDay, $toDay) {
                $start = Carbon::parse($leave->start_date)->startOfDay()->max($fromDay);
                $end   = Carbon::parse($leave->end_date)->startOfDay()->min($toDay);
                return (int) $start->diffInDays($end) + 1;
            });
    }

    /* ------------------------------------------------------------------
     | Endpoints
     ------------------------------------------------------------------ */

    public function metricCard(Request $request)
    {
        [, $from, $to] = $this->period($request);
        [$projectId, $departmentId] = $this->filters($request);

        try {
            // done within the selected period
            $completed = $this->scopeTasks(Tasks::query(), $projectId, $departmentId)
                ->where('status', 'done')
                ->whereBetween('updated_at', [$from, $to])
                ->count();

            // snapshot: overdue right now
            $overdue = $this->scopeTasks(Tasks::query(), $projectId, $departmentId)
                ->where('status', '!=', 'done')
                ->where('due_date', '<', now())
                ->count();

            // snapshot: pending right now (department only)
            $pendingLeave = $this->scopeLeave(Leave_requests::query(), $departmentId)
                ->where('status', 'pending')
                ->count();

            return response()->json([
                'taskCompleted'       => $completed,
                'overdueTasks'        => $overdue,
                'pendingLeaveRequest' => $pendingLeave,
                'leaveDaysTaken'      => $this->leaveDaysBetween($from, $to, $departmentId),
            ], 200);
        } catch (QueryException $e) {
            Log::error('metricCard failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load metrics'], 500);
        }
    }

    public function projectCompletion(Request $request)
    {
        [$projectId, $departmentId] = $this->filters($request);

        try {
            $projects = Projects::query()
                ->when($projectId, fn($q) => $q->where('id', $projectId))
                ->withCount([
                    'tasks as total_tasks' => fn($q) => $this->scopeTasks($q, null, $departmentId),
                    'tasks as completed_tasks_count' => fn($q) => $this->scopeTasks($q, null, $departmentId)
                        ->where('status', 'done'),
                ])
                ->get();

            return response()->json($projects, 200);
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load project completion'], 500);
        }
    }

    public function tasksByStatus(Request $request)
    {
        [$projectId, $departmentId] = $this->filters($request);

        try {
            $tasks = $this->scopeTasks(Tasks::query(), $projectId, $departmentId)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            return response()->json([
                'todo'        => $tasks['todo'] ?? 0,
                'in_progress' => $tasks['in_progress'] ?? 0,
                'review'      => $tasks['review'] ?? 0,
                'done'        => $tasks['done'] ?? 0,
            ], 200);
        } catch (QueryException $e) {
            Log::error('tasksByStatus failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load tasks by status'], 500);
        }
    }

    public function employeesTaskOverview(Request $request)
    {
        [$projectId, $departmentId] = $this->filters($request);

        try {
            $byProject = fn($q) => $q->when($projectId, fn($q) => $q->where('project_id', $projectId));

            $employees = User::query()
                ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
                ->withCount([
                    'tasks as total_tasks_count' => $byProject,
                    'tasks as completed_tasks_count' => fn($q) => $byProject($q)->where('status', 'done'),
                    'tasks as overdue_tasks_count' => fn($q) => $byProject($q)
                        ->where('status', '!=', 'done')
                        ->where('due_date', '<', now()),
                ])
                ->orderByDesc('overdue_tasks_count')
                ->orderByDesc('total_tasks_count')
                ->get();

            return response()->json($employees, 200);
        } catch (QueryException $e) {
            Log::error('employeesTaskOverview failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch employee task overview'], 500);
        }
    }

    public function leaveThisWeek(Request $request)
    {
        [, $departmentId] = $this->filters($request);

        $start = now()->startOfWeek();
        $end   = now()->endOfWeek();

        try {
            $leaveRequests = $this->scopeLeave(Leave_requests::with(['user', 'leave_type']), $departmentId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->get();

            return response()->json($leaveRequests, 200);
        } catch (QueryException $e) {
            Log::error('leaveThisWeek failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch employees on leave this week'], 500);
        }
    }

    public function leaveByMonth(Request $request)
    {
        [, $departmentId] = $this->filters($request);

        try {
            $year = (int) $request->query('year', now()->year);
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to   = $from->copy()->endOfYear();

            $rows = $this->scopeLeave(Leave_requests::query(), $departmentId)
                ->selectRaw('MONTH(start_date) as month, status, COUNT(*) as total')
                ->whereBetween('start_date', [$from, $to])
                ->whereIn('status', ['approved', 'pending', 'rejected'])
                ->groupBy('month', 'status')
                ->get();

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $months[$m] = [
                    'month'    => Carbon::create($year, $m, 1)->format('M'),
                    'approved' => 0,
                    'pending'  => 0,
                    'rejected' => 0,
                ];
            }

            foreach ($rows as $row) {
                $months[$row->month][$row->status] = (int) $row->total;
            }

            return response()->json(array_values($months));
        } catch (QueryException $e) {
            Log::error('leaveByMonth failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load leave by month'], 500);
        }
    }

    public function needAttention(Request $request)
    {
        [$projectId, $departmentId] = $this->filters($request);

        try {
            $tasks = $this->scopeTasks(Tasks::with(['project', 'assignee']), $projectId, $departmentId)
                ->where('status', '!=', 'done')
                ->where('due_date', '<', now())
                ->get();

            return response()->json($tasks, 200);
        } catch (QueryException $e) {
            Log::error('needAttention failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch tasks that need attention'], 500);
        }
    }

    public function summaryMetrics(Request $request)
    {
        try {
            [$days, $from, $to] = $this->period($request);
            [$projectId, $departmentId] = $this->filters($request);

            $prevTo   = $from->copy()->subDay()->endOfDay();
            $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

            // same filters on both periods so the comparison is fair
            $cur  = $this->summaryFor($from, $to, $projectId, $departmentId);
            $prev = $this->summaryFor($prevFrom, $prevTo, $projectId, $departmentId);

            $definitions = [
                'totalTasks'     => ['total-tasks',      'Total tasks',      'percent', false, null],
                'tasksCompleted' => ['tasks-completed',  'Tasks completed',  'percent', false, null],
                'overdueTasks'   => ['overdue-tasks',    'Overdue tasks',    'count',   true,  'fewer is better'],
                'leaveRequests'  => ['leave-requests',   'Leave requests',   'count',   false, 'watch'],
                'leaveDaysTaken' => ['leave-days-taken', 'Leave days taken', 'percent', true,  'fewer is better'],
            ];

            $rows = [];
            foreach ($definitions as $key => [$id, $label, $type, $lowerIsBetter, $note]) {
                $value    = $cur[$key];
                $previous = $prev[$key];

                $diff = $type === 'percent'
                    ? ($previous > 0 ? round((($value - $previous) / $previous) * 100) : null)
                    : $value - $previous;

                if ($diff === null || $diff == 0) {
                    $change = null;
                } else {
                    $up = $diff > 0;

                    $tone = $key === 'leaveRequests'
                        ? 'watch'
                        : (($lowerIsBetter ? $up : !$up) ? 'bad' : 'good');

                    $change = [
                        'direction' => $up ? 'up' : 'down',
                        'amount'    => abs($diff),
                        'unit'      => $type === 'percent' ? '%' : '',
                        'tone'      => $tone,
                        'note'      => $note,
                    ];
                }

                $rows[] = ['id' => $id, 'label' => $label, 'value' => $value, 'change' => $change];
            }

            return response()->json($rows);
        } catch (QueryException $e) {
            Log::error('summaryMetrics failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load summary metrics'], 500);
        }
    }

    private function summaryFor(Carbon $from, Carbon $to, ?int $projectId, ?int $departmentId): array
    {
        $tasks = fn() => $this->scopeTasks(Tasks::query(), $projectId, $departmentId);

        return [
            'totalTasks'     => $tasks()->whereBetween('created_at', [$from, $to])->count(),
            'tasksCompleted' => $tasks()->where('status', 'done')
                ->whereBetween('updated_at', [$from, $to])->count(),
            'overdueTasks'   => $tasks()->where('status', '!=', 'done')
                ->whereBetween('due_date', [$from, $to])
                ->where('due_date', '<', now())->count(),
            'leaveRequests'  => $this->scopeLeave(Leave_requests::query(), $departmentId)
                ->whereBetween('created_at', [$from, $to])->count(),
            'leaveDaysTaken' => $this->leaveDaysBetween($from, $to, $departmentId),
        ];
    }
}
