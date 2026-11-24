<?php 
namespace App\Services;

use App\Models\{Leave_requests, User};
use Carbon\{Carbon, CarbonPeriod};

class LeaveBalance
{
    public static function workingDays(Carbon $from, Carbon $to, Carbon $winStart, Carbon $winEnd): int
    {
        $s = $from->copy()->max($winStart);
        $e = $to->copy()->min($winEnd);
        if ($s->gt($e)) return 0;
        return collect(CarbonPeriod::create($s, $e))->filter(fn($d) => $d->isWeekday())->count();
    }

    public function forMonth(User $user, Carbon $today): array
    {
        $start = $today->copy()->startOfMonth();
        $end   = $today->copy()->endOfMonth();

        $requests = Leave_requests::with('leave_type:id,name')
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->map(fn($l) => [
                'status'       => $l->status,
                'leave_type'   => $l->leave_type->name,
                'start_date'   => $l->start_date->toDateString(),
                'end_date'     => $l->end_date->toDateString(),
                'working_days' => self::workingDays($l->start_date, $l->end_date, $start, $end),
            ]);

        $allowance = (int) config('leave.monthly_allowance', 4); // placeholder: your real policy
        $approved  = $requests->where('status', 'approved');
        $pending   = $requests->where('status', 'pending');
        $used      = $approved->sum('working_days');

        return [
            'period'            => $today->format('Y-m'),
            'allowance'         => $allowance,
            'used'              => $used,
            'remaining'         => max(0, $allowance - $used),
            'pending_days'      => $pending->sum('working_days'),
            'approved_requests' => $approved->map(fn($r) => collect($r)->except('status'))->values(),
            'pending_requests'  => $pending->map(fn($r) => collect($r)->except('status'))->values(),
        ];
    }
}