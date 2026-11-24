<?php

namespace App\Http\Controllers;

use App\Models\Leave_requests;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $year = (int) $request->query('year', now()->year);
            $year = ($year >= 2000 && $year <= 2100) ? $year : now()->year;

            // a manager without a department must not fall through to "everything"
            if ((int) $user->role_id === 2 && !$user->department_id) {
                return response()->json($this->empty($year));
            }

            return response()->json([
                'year'          => $year,
                'tasksByStatus' => $this->tasksByStatus($user),
                'stats'         => $this->stats($user),
                'leaveUsage'    => $this->leaveUsage($user, $year),
            ]);
        } catch (\Throwable $e) {
            Log::error('dashboard failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load dashboard.'], 500);
        }
    }

    /* ---------- scoping by role ---------- */

    private function scopeTasks($query, $user)
    {
        return match ((int) $user->role_id) {
            1       => $query,
            2       => $query->whereHas('assignee', fn($u) => $u->where('department_id', $user->department_id)),
            default => $query->whereHas('assignee', fn($u) => $u->where('users.id', $user->id)),
        };
    }

    private function scopeLeave($query, $user)
    {
        return match ((int) $user->role_id) {
            1       => $query,
            2       => $query->whereHas('user', fn($u) => $u->where('department_id', $user->department_id)),
            default => $query->whereHas('user', fn($u) => $u->where('users.id', $user->id)),
        };
    }

    /* ---------- data ---------- */

    private function tasksByStatus($user): array
    {
        $rows = $this->scopeTasks(Tasks::query(), $user)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'todo'        => (int) ($rows['todo'] ?? 0),
            'in_progress' => (int) ($rows['in_progress'] ?? 0),
            'review'      => (int) ($rows['review'] ?? 0),
            'done'        => (int) ($rows['done'] ?? 0),
        ];
    }

/** Approved leave days per leave type, clipped to the given year. */
    private function leaveUsage($user, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->startOfDay();

        $leaves = $this->scopeLeave(Leave_requests::query()->with('leave_type:id,name'), $user)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get();

        return $leaves
            ->groupBy(fn($l) => $l->leave_type?->name ?? 'Other')
            ->map(fn($group, $name) => [
                'name' => $name,
                'days' => (int) $group->sum(function ($l) use ($yearStart, $yearEnd) {
                    $start = Carbon::parse($l->start_date)->startOfDay()->max($yearStart);
                    $end   = Carbon::parse($l->end_date)->startOfDay()->min($yearEnd);
                    return (int) $start->diffInDays($end) + 1;
                }),
            ])
            ->sortByDesc('days')
            ->values()
            ->all();
    }

    private function empty(int $year): array
    {
        return [
            'year'          => $year,
            'stats'         => ['pendingLeave' => 0, 'overdueTasks' => 0, 'activeProjects' => 0, 'teamMembers' => 0],
            'tasksByStatus' => ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0],
            'leaveUsage'    => [],
        ];
    }

    private function stats($user): array
    {
        $role = (int) $user->role_id;

        // Pending leave: admin = all, manager = their department (to approve), employee = their own
        $pendingLeave = $this->scopeLeave(Leave_requests::query(), $user)
            ->where('status', 'pending')
            ->count();

        // Overdue tasks, same scoping as the charts
        $overdue = $this->scopeTasks(Tasks::query(), $user)
            ->where('status', '!=', 'done')
            ->where('due_date', '<', now())
            ->count();

        // Active projects: same visibility rules as the projects page
        $projects = Projects::query()->where('status', 'active');
        if ($role === 2) {
            $projects->where('created_by', $user->id);
        } elseif ($role !== 1) {
            $projects->whereHas('members', fn($m) => $m->where('users.id', $user->id));
        }

        // Team members: active, non-admin users
        $team = User::query()->where('status', 'active')->where('role_id', '!=', 1);
        if ($role !== 1) {
            $team->where('department_id', $user->department_id);
        }

        return [
            'pendingLeave'   => $pendingLeave,
            'overdueTasks'   => $overdue,
            'activeProjects' => $projects->count(),
            'teamMembers'    => $team->count(),
        ];
    }
}
