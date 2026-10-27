<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(['message' => 'Projects created (stub)']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        return response()->json(['message' => 'Projects created (stub)']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Projects $projects)
    {
        //
        return response()->json(['message' => 'Projects created (stub)']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Projects $projects)
    {
        //
        return response()->json(['message' => 'Projects created (stub)']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Projects $projects)
    {
        //
        return response()->json(['message' => 'Projects created (stub)']);
    }

    public function manageMembers(Request $request)
    {
        return response()->json(['message' => 'Projects created (stub)']);
    }
}
