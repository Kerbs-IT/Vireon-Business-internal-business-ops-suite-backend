<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectTaskRequest;
use App\Models\Projects;
use App\Models\Tasks;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($projectId)
    {
        //
        try{
            $user = Auth::user();
            $project = Projects::findOrFail($projectId);

            if ($user->role_id == 2 && $project->created_by != $user->id) {
                return response()->json(['message' => 'This project is not yours.'], 403);
            }

            $tasks = Tasks::with([
                'assignee:id,first_name,last_name,position',
                'comments.user:id,first_name,last_name,position',
            ])
                ->where('project_id', $project->id)
                ->when($user->role_id == 3, fn($query) => $query->where('assigned_to', $user->id))
                ->get();

                return response()->json($tasks,200);

        }catch(Exception $e ){
            return response() -> json(['message' => $e->getMessage()],403);
        }catch(QueryException $e){
            return response()->json(['message' => $e->getMessage()], 403);
        }catch(Throwable $e){
            return response()->json(['message' => $e->getMessage()], 403);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectTaskRequest $request)
    {
        try {
            $task = Tasks::create($request->validated());

            return response()->json([
                'message' => 'Task created successfully.',
                'task'    => $task->load('assignee'),
            ], 201);
        } catch (QueryException $e) {
            // DB problems: bad foreign key, constraint violation, etc.
            Log::error('Failed to create task (database)', [
                'error'   => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Could not save the task. Please check your input and try again.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Failed to create task', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong while creating the task.',
            ], 500);
        }
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
    public function update(ProjectTaskRequest $request, Tasks $task)
    {
        try {
            $user = $request->user();

            // manager: only tasks in projects they created
            if ($user->role_id == 2 && $task->project->created_by != $user->id) {
                return response()->json([
                    'message' => 'You can only edit tasks in projects you created.',
                ], 403);
            }

            // employee: no editing
            if ($user->role_id == 3) {
                return response()->json([
                    'message' => 'You are not authorized to edit tasks.',
                ], 403);
            }

            $task->update($request->validated());

            return response()->json([
                'message' => 'Task updated successfully.',
                'task'    => $task->fresh()->load('assignee'),
            ], 200);
        } catch (QueryException $e) {
            Log::error('Failed to update task (database)', [
                'error'   => $e->getMessage(),
                'payload' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Could not update the task. Please check your input and try again.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Failed to update task', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong while updating the task.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Tasks $task)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role_id, [1, 2])) {
                return response()->json(['message' => 'Not authorized.'], 403);
            }

            if ($user->role_id == 2 && $task->project?->created_by !== $user->id) {
                return response()->json(
                    ['message' => 'You can only delete tasks in your own projects.'],
                    403
                );
            }

            $task->delete(); // soft delete

            return response()->json(['message' => 'Project task deleted.']);
        } catch (\Throwable $e) {
            Log::error('Delete project task failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to delete the project task.'], 500);
        }
    }

    // this function is specifically for kanban drag n drop

    public function updateStatus(Request $request, Tasks $task)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:todo,in_progress,review,done'],
        ]);

        $task->update($validated);

        return response()->json($task->fresh());
    }
   
}
