<?php

namespace App\Http\Controllers;

use App\Models\Tasks;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(['message' => 'Tasks created (stub)']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        return response()->json(['message' => 'Tasks created (stub)']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Tasks $tasks)
    {
        //
        return response()->json(['message' => 'Tasks created (stub)']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tasks $tasks)
    {
        //
        return response()->json(['message' => 'Tasks created (stub)']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tasks $tasks)
    {
        //
        return response()->json(['message' => 'Tasks created (stub)']);
    }
}
