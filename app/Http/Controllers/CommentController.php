<?php

namespace App\Http\Controllers;

use App\Models\Comments;
use App\Models\Tasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Tasks $task)
    {
        try {
            $user = $request->user();

            // manager: only tasks in projects they created
            if ($user->role_id == 2 && $task->project->created_by != $user->id) {
                return response()->json(['message' => 'This task is not in your project.'], 403);
            }

            // employee: only tasks assigned to them
            if ($user->role_id == 3 && $task->assigned_to != $user->id) {
                return response()->json(['message' => 'You can only comment on your own tasks.'], 403);
            }

            $data = $request->validate([
                'comments' => ['required', 'string', 'max:1000'],
            ]);

            $comment = $task->comments()->create([
                'user_id'  => $user->id,
                'comments' => $data['comments'],
            ]);

            return response()->json([
                'message' => 'Comment added.',
                'data'    => $comment->load('user:id,first_name,last_name,position'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Store comment failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to add the comment.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Comments $comment)
    {
        try {
            $user = $request->user();

            // only the author (or an admin) can edit
            if ($comment->user_id != $user->id && $user->role_id != 1) {
                return response()->json(['message' => 'You can only edit your own comments.'], 403);
            }

            $data = $request->validate([
                'comments' => ['required', 'string', 'max:1000'],
            ]);

            $comment->update($data);

            return response()->json([
                'message' => 'Comment updated.',
                'data'    => $comment->load('user:id,first_name,last_name,position'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Update comment failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to update the comment.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
