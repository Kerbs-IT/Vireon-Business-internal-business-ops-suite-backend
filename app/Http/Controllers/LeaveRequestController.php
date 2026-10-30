<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveRequest;
use App\Models\Leave_requests;
use Exception;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(['message' => 'Leave Requests created (stub)']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeaveRequest $request)
    {
        //
        try {
            $department = Leave_requests::create($request->validated());

            return response()->json([
                'message' => 'Leave Request successfully created.'
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
        return response()->json(['message' => 'Leave Requests created (stub)']);
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
        return response()->json(['message' => 'Leave Requests created (stub)'],200);
    }

    public function reject(Request $request, Leave_requests $leave_requests)
    {
        return response()->json(['message' => 'Leave Requests created (stub)'], 200);
    }
}
