<?php

namespace App\Http\Controllers;

use App\Models\Leave_types;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(['message' => 'Leave type created (stub)']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        return response()->json(['message' => 'Leave type created (stub)']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Leave_types $leave_types)
    {
        //
        return response()->json(['message' => 'Leave type created (stub)']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Leave_types $leave_types)
    {
        //
        return response()->json(['message' => 'Leave type created (stub)']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Leave_types $leave_types)
    {
        //
        return response()->json(['message' => 'Leave type created (stub)']);
    }
}
