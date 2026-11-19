<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Departments;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        try{
            $departments = Departments::with('manager')
            -> withCount(
                ['users as employee_count' => function($query){
                    $query ->wherein('role_id',[2,3]);
                }]
            ) ->
            when($request -> user()->role_id == 2, function ($query) use ($request) {
                $query->where('manager_id', $request->user()->id);
            })
            -> when($request->boolean('archived'), fn($q) => $q->onlyTrashed()) 
            -> get();

            return response()->json(["departments" => $departments],200);

        }catch(Exception $e){
            return response() -> json(["message" => $e -> getMessage()],500);
        }
       
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request)
    {
        try {
            $department = Departments::create($request->validated());

            return response()->json([
                'department' => $department->load('manager'),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Departments $departments)
    {
        //
        return response()->json(['message' => 'Department created (stub)'],201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Departments $department)
    {
        try {
            $department->update($request->validated());

            return response()->json([
                'department' => $department->load('manager'),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Departments $department)
    {
        try {
            if (!in_array($request->user()->role_id, [1], true)) {   // allow-list, admin only
                return response()->json(['message' => 'Not authorized.'], 403);
            }

            // $count = User::where('department_id', $departments->id)->count();
            // if ($count > 0) {
            //     return response()->json([
            //         'message' => "Cannot archive: {$count} employee(s) still belong to this department. Reassign them first.",
            //     ], 422);
            // }

            $department->delete();

            return response()->json(['message' => 'Department archived.']);
        } catch (\Throwable $e) {
            Log::error('Archive department failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to archive the department.'], 500);
        }
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();
        abort_unless(in_array($user->role_id, [1]), 403);

        $department = Departments::onlyTrashed()->findOrFail($id);

        // a manager can only restore their own projects
        abort_if($user->role_id == 2 && $department->created_by !== $user->id, 403);

        $department->restore();

        return response()->json(['message' => 'Department restored.']);
    }
}
