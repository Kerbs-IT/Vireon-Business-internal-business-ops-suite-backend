<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function(){

    Route::post('/logout',[AuthController::class,'logout']);
    Route::get('/me',[AuthController::class,'me']);


    // All authenticated users
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/projects', [ProjectController::class, 'index']);

    // Admin only routes
    Route::middleware('role:admin') -> group(function(){
        
        // Route::post('/departments', [DepartmentController::class, 'store']);
        // Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        // Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        // maangers list
        Route::get('/managers', [EmployeeController::class,'managers']);

        
    });

    // admin and manager routes
    Route::middleware('role:admin,manager')->group(function(){
        Route::apiResource('departments', DepartmentController::class);


        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

    
        Route::post('/deactivate/{id}',[AuthController::class,'deactivate']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::put('/projects/{id}', [ProjectController::class, 'update']);
        Route::post('/projects/{id}/members', [ProjectController::class, 'manageMembers']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
        Route::put('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
        Route::put('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
        // roles
        Route::get('/roles',[RolesController::class,'index']);
    });



    // Employee-only-ish (any authenticated user files their own leave)
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
});

Route::post('/login',[AuthController::class,'login']);
