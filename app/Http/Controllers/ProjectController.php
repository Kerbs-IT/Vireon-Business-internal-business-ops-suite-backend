<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project_members;
use App\Models\Projects;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\now;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try{
            $projects = Projects::with('members')->when(Auth::user()->role_id == 2, function ($query) {
                $query-> where('created_by', Auth::user()->id);
            })->get();

            return response() -> json($projects,200);

        }catch( Exception $e){
            return response() -> json(['message' => $e->getMessage() ?? 'Unable to get the list of projects.'],403);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectRequest $request)
    {
        //
        $user = Auth::user();

        if($user->role_id === 3){
            return response()->json(['message' => 'You are not authorize to create a project']);
        }

        try{

            $project = DB::transaction(function () use ($request) {
                $data = $request->safe()->except('members');

                $project = Projects::create([
                    ...$data,
                    'start_date' => now(),
                    'created_by' => $request->user()->id,
                ]);

                $memberIds = collect($request->validated('members'))
                    ->push($request->user()->id)
                    ->unique()
                    ->values();

                $project->members()->attach($memberIds);

                return $project;
            });

            return response()->json([
                'message' => 'Project created successfully.',
                'data'    => $project->load('members:id,first_name,last_name,position'),
            ], 201);

            return response() -> json(['message' => 'Successfully created a new project'],200);
        }catch(Exception $e){
                return response()->json(['message' => $e->getMessage()?? 'Error occured: unable to create a project'],403);
        }
        
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
   

    public function update(ProjectRequest $request, Projects $project)
    {
        try {
            $user = $request->user();

            // managers can only edit projects they created (same rule as index)
            if ($user->role_id == 2 && $project->created_by != $user->id) {
                return response()->json([
                    'message' => 'You can only edit projects you created.',
                ], 403);
            }

            DB::transaction(function () use ($request, $project) {
                // members goes to the pivot table, everything else to the project
                $project->update($request->safe()->except('members'));

                // adds newly checked members, removes unchecked ones
                $project->members()->detach();
                $project->members()->attach($request->validated('members'));
            });

            return response()->json([
                'message' => 'Project updated successfully.',
                'data'    => $project->fresh()->load('members:id,first_name,last_name,position'),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Update project failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to update the project.'], 500);
        }
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
