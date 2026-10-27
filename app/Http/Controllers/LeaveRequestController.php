<?php

namespace App\Http\Controllers;

use App\Models\Leave_requests;
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
    public function store(Request $request)
    {
        //
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
    public function update(Request $request, Leave_requests $leave_requests)
    {
        //
        return response()->json(['message' => 'Leave Requests created (stub)']);
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
