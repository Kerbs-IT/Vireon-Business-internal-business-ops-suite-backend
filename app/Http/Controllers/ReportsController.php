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

use function Illuminate\Support\now;

class ReportsController extends Controller
{
    //

    public function metricCard(Request $request)
    {
        // 1. Define the period
        $days = (int) rtrim($request->query('days', '30d'), 'd'); // "30d" -> 30
        $days = in_array($days, [7, 30, 90]) ? $days : 30;        // whitelist

        $to   = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        try {
            $task = Tasks::where('status', 'done')->count();
            $overdueTask = Tasks::where('status', "!=", 'done')->where('due_date', '<', now())->count();
            $pendingLeaveRequest = Leave_requests::where('status', 'pending')->count();
            $leaveDaysTaken = Leave_requests::where('status', 'approved')
                ->where('start_date', '<=', $to)
                ->where('end_date', '>=', $from)
                ->get()
                ->sum(function ($leave) use ($from, $to) {
                    $start = max($leave->start_date, $from);
                    $end   = min($leave->end_date, $to);
                    return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
                });

            return response()->json(['taskCompleted' => $task, 'overdueTasks' => $overdueTask, 'pendingLeaveRequest' => $pendingLeaveRequest, 'leaveDaysTaken' => $leaveDaysTaken,],200);
        } catch (QueryException $e) {
            Log::error('metricCard failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load metrics'], 500);
        }
    }

    public function projectCompletion(Request $request)
    {

        try {

            $projects = Projects::withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks_count' => fn($query) => $query->where('status', 'done')
            ])->get();


            return response()->json($projects,200);
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load project completion'], 500);
        }
    }

    public function tasksByStatus()
    {
        try {

            $tasks = Tasks::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');


            return response()->json([
                'todo' => $tasks['todo'] ?? 0,
                'in_progress' => $tasks['in_progress'] ?? 0,
                'review' => $tasks['review'] ?? 0,
                'done' => $tasks['done'] ?? 0
            ],200);
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load tasks by status '], 500);
        }
    }
    public function employeesTaskOverview()
    {
        try {

            $employee = User::withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn($query) => $query->where('status', 'done'),
                'tasks as overdue_tasks_count' => fn($query) => $query->where('status', '!=', 'done')->where('due_date', '<', now())
            ])->orderByDesc('overdue_tasks_count')
                ->get();


            return response()->json(
                $employee,200
            );
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch employee task status by status '], 500);
        }
    }
    public function leaveThisWeek()
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        try {
            $leaveRequests = Leave_requests::with(['user', 'leave_type'])->where('status', 'approved')
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->get();

            return response()->json(
                $leaveRequests,200
            );
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch employee on leave this week '], 500);
        }
    }

    public function leaveByMonth(Request $request)
    {
        try {
            $year = (int) $request->query('year', now()->year);
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to   = $from->copy()->endOfYear();

            // One query: count per month and status
            $rows = Leave_requests::selectRaw('MONTH(start_date) as month, status, COUNT(*) as total')
                ->whereBetween('start_date', [$from, $to])
                ->whereIn('status', ['approved', 'pending', 'rejected'])
                ->groupBy('month', 'status')
                ->get();

            // Start with all 12 months at zero so empty months still show on the chart
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
        try {

            $tasks = Tasks::with(['project', 'assignee'])
            ->where('status', '!=', 'done')
            ->where('due_date', '<', now())->get();

            return response() -> json($tasks,200);
        } catch (QueryException $e) {
            Log::error('projectCompletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to fetch tasks that need attention '], 500);
        }
    }


    public function summaryMetrics(Request $request)
    {
        try {
            $days = (int) rtrim($request->query('days', '30d'), 'd');
            $days = in_array($days, [7, 30, 90]) ? $days : 30;

            $to       = now()->endOfDay();
            $from     = now()->subDays($days - 1)->startOfDay();
            $prevTo   = $from->copy()->subDay()->endOfDay();
            $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

            $cur  = $this->summaryFor($from, $to);
            $prev = $this->summaryFor($prevFrom, $prevTo);

            // key => [label, how to show the change]
            $definitions = [
                // key            => [id, label, type, lowerIsBetter, note]
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

                if ($type === 'percent') {
                    $diff = $previous > 0 ? round((($value - $previous) / $previous) * 100) : null;
                } else {
                    $diff = $value - $previous;
                }

                // null = no comparison possible (previous was 0) or no change at all
                if ($diff === null || $diff == 0) {
                    $change = null;
                } else {
                    $up = $diff > 0;

                    if ($key === 'leaveRequests') {
                        $tone = 'watch';
                    } else {
                        $isBad = $lowerIsBetter ? $up : !$up;
                        $tone  = $isBad ? 'bad' : 'good';
                    }

                    $change = [
                        'direction' => $up ? 'up' : 'down',
                        'amount'    => abs($diff),
                        'unit'      => $type === 'percent' ? '%' : '',
                        'tone'      => $tone,
                        'note'      => $note,
                    ];
                }

                $rows[] = [
                    'id'     => $id,
                    'label'  => $label,
                    'value'  => $value,
                    'change' => $change,
                ];
            }

            return response()->json($rows);
        } catch (QueryException $e) {
            Log::error('summaryMetrics failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load summary metrics'], 500);
        }
    }

    private function summaryFor(Carbon $from, Carbon $to): array
    {
        $leaveDays = Leave_requests::where('status', 'approved')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get()
            ->sum(function ($leave) use ($from, $to) {
                $start = Carbon::parse($leave->start_date)->max($from);
                $end   = Carbon::parse($leave->end_date)->min($to);
                return (int) $start->diffInDays($end) + 1;
            });

        return [
            'totalTasks'     => Tasks::whereBetween('created_at', [$from, $to])->count(),
            'tasksCompleted' => Tasks::where('status', 'done')
                ->whereBetween('updated_at', [$from, $to])->count(),
            'overdueTasks'   => Tasks::where('status', '!=', 'done')
                ->whereBetween('due_date', [$from, $to])
                ->where('due_date', '<', now())->count(),
            'leaveRequests'  => Leave_requests::whereBetween('created_at', [$from, $to])->count(),
            'leaveDaysTaken' => $leaveDays,
        ];
    }
}
