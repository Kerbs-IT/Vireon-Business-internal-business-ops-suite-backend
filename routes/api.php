<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Prompts\Task;

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
        Route::apiResource('projects',ProjectController::class);
        // Route::post('/projects', [ProjectController::class, 'store']);

        // Route::post('/projects/{id}/members', [ProjectController::class, 'manageMembers']);
        Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::apiResource('tasks',TaskController::class);
        // Route::post('/tasks', [TaskController::class, 'store']);
        // Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

        Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);
        Route::put('/comments/{comment}', [CommentController::class, 'update']);

        Route::put('/leave-requests/{leave_requests}/approve', [LeaveRequestController::class, 'approve']);
        Route::put('/leave-requests/{leave_requests}/reject', [LeaveRequestController::class, 'reject']);
        // roles
        Route::get('/roles',[RolesController::class,'index']);

        // team request
        Route::get('/team-leave-requests',[LeaveRequestController::class, 'teamLeaveRequest']);
        Route::get('/leave-calendar',[LeaveRequestController::class,'calendar']);

        // reports
        Route::get('/report/metric-card',[ReportsController::class, 'metricCard']);
        Route::get('/report/project-completion',[ReportsController::class,'projectCompletion']);
        Route::get('/report/task-by-status',[ReportsController::class, 'tasksByStatus']);
        Route::get('/report/employee-tasks-overview', [ReportsController::class,'employeesTaskOverview']);
        Route::get('/report/leave',[ReportsController::class, 'leaveThisWeek']);
        Route::get('/report/leave-by-month', [ReportsController::class, 'leaveByMonth']);
        Route::get('/report/need-attention', [ReportsController::class, 'needAttention']);
        Route::get('/report/summary-metrics', [ReportsController::class, 'summaryMetrics']);
    });

    Route::middleware('role:admin,manager,employee')-> group(function(){
        Route::get('/my-leave-request',[LeaveRequestController::class,'index']);
        Route::apiResource('leaveRequests',LeaveRequestController::class);

        Route::get('/leave-types', [LeaveTypeController::class,'index']);
    });



    // Employee-only-ish (any authenticated user files their own leave)
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
});

Route::post('/login',[AuthController::class,'login']);
