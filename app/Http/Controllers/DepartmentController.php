<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Departments;
use Exception;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try{
            $departments = Departments::with('manager')
            -> withCount(
                ['users as employee_count' => function($query){
                    $query ->wherein('role_id',[2,3]);
                }]
            ) -> get();

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
    public function destroy(Departments $departments)
    {
        //
        return response()->json(['message' => 'Department created (stub)'], 201);
    }
}
