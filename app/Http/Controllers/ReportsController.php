<?php

namespace App\Http\Controllers;

use App\Models\Leave_requests;
use App\Models\Tasks;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\now;

class ReportsController extends Controller
{
    //

    public function metricCard(Request $request){
        // 1. Define the period
        $days = (int) rtrim($request->query('days', '30d'), 'd'); // "30d" -> 30
        $days = in_array($days, [7, 30, 90]) ? $days : 30;        // whitelist

        $to   = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        try{
            $task = Tasks::where('status', 'done')->count();
            $overdueTask = Tasks::where('status', "!=", 'done')->where('due_date', '<', now())->count();
            $pendingLeaveRequest = Leave_requests::where('status','pending')->count();
            $leaveDaysTaken = Leave_requests::where('status', 'approved')
                ->where('start_date', '<=', $to)
                ->where('end_date', '>=', $from)
                ->get()
                ->sum(function ($leave) use ($from, $to) {
                    $start = max($leave->start_date, $from);
                    $end   = min($leave->end_date, $to);
                    return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
                });

            return response() -> json(['taskCompleted' => $task,'overdueTasks' => $overdueTask,'pendingLeaveRequest' => $pendingLeaveRequest, 'leaveDaysTaken' => $leaveDaysTaken,]);
            
        } catch (QueryException $e) {
            Log::error('metricCard failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load metrics'], 500);
        }
    }
   
}
