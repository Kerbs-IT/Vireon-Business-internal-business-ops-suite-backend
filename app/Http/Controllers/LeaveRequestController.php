<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveRequest;
use App\Models\Leave_requests;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Illuminate\Support\now;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try{
            $myLeaveRequest = Leave_requests::with('leave_type')->where('user_id',Auth::user()->id)-> get();

            return response()->json(['myRequests' => $myLeaveRequest], 200);

        }catch(Exception $e){
            return response()->json(['message' => $e->getMessage()],403);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeaveRequest $request)
    {
        try {
            Leave_requests::create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
                'status'  => 'pending',
            ]);

            return response()->json(['message' => 'Leave request submitted successfully.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Something went wrong.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Leave_requests $leave_requests)
    {
        //
        return response()->json(['message' => 'Leave Requests created (stub)']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeaveRequest $request, Leave_requests $leaveRequest)
    {
        try{
            $leaveRequest->update($request->validated());

            return response()->json(['leaveRequest' => $leaveRequest],200);
            
        }catch(Exception $e){
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Leave_requests $leave_requests)
    {
        //
        return response()->json(['message' => 'Leave Requests created (stub)']);
    }

    public function approve(Request $request, Leave_requests $leave_requests)
    {
        return $this->review($request, $leave_requests, 'approved');
    }

    public function reject(Request $request, Leave_requests $leave_requests)
    {
        return $this->review($request, $leave_requests, 'rejected');
    }

    // create helper function to review the request
    private function review(Request $request, Leave_requests $leave_requests,$status){

        // first check the use logged in
        $user = Auth::user();

        if($user->role_id === 3){
            return response()->json(['message' => 'You are not authorized to review leave requests.'], 403);
        }

        if($user->role_id == 2 && $user->department_id != $leave_requests->user->department_id){
            return response()->json(['message' => 'This leave request is not under your department.'], 403);
        }

        if($leave_requests->status != 'pending'){
            return response()->json(['message' => 'This leave request has already been reviewed.'], 422);
        }

        $leave_requests->update([
            'status' => $status,
            'approved_by' => $user->id,
            'approved_date' => now(),
            'comments' => $request->comment
        ]);

        return response()->json(['message' => "Leave request {$status} successfully."]);
    }

    public function teamLeaveRequest(){
        try{
            $user = Auth::user();
            if($user->role_id != 1 && $user->role_id != 2){
                return response()->json(['message' => 'You are not authorized to view team leave request.'], 403);
            }

            $teamLeaveRequest = Leave_requests::with('user.department','leave_type')
                                ->where('status', 'pending')
                                ->when($user->role_id == 2, function($query) use ($user){
                                    $query->whereHas('user', function($q) use ($user){
                                        $q->where('department_id',$user->department_id);
                                    });
                                })
                                ->where('user_id', '!=', $user->id)
                                ->get();


            return response()->json($teamLeaveRequest, 200);
        }catch(Exception $e){
            return response() -> json(['message'=> $e->getMessage()],403);
        }
    }

    public function calendar(Request $request)
    {
        $user = $request->user();

        if ($user->role_id == 3) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date|after_or_equal:start',
        ]);

        $leaves = Leave_requests::with('user:id,first_name,last_name,department_id', 'leave_type:id,name')
            ->where('status', 'approved')
            // overlap: starts before the range ends AND ends after the range starts
            ->where('start_date', '<=', $request->end)
            ->where('end_date', '>=', $request->start)
            ->when($user->role_id == 2, function ($query) use ($user) {
                $query->whereHas('user', fn($q) => $q->where('department_id', $user->department_id));
            })
            ->get();

        return response()->json($leaves);
    }

    
}
