<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // get the current user
        

        try{
            $employees = User::with('department', 'role')
                ->where('role_id',"!=" ,1)
                ->when(Auth::user()-> role_id == 2, function ($query) {
                    $query -> where('department_id', Auth::user()-> department_id);
                })
                ->get();


            return response()->json(['employees' => $employees]);
        }catch(Exception $e){
            return response() -> json(['error' => $e->getMessage()]);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = User::create([
                ...$request->validated(),
                'password' => Str::random(40),
            ]);
        } catch (Throwable $e) {
            report($e); // logs it without crashing
            return response()->json([
                'message' => 'Failed to create employee. Please try again.',
            ], 500);
        }

        $emailSent = false;

        try {
            $status = Password::sendResetLink(['email' => $employee->email]);
            $emailSent = $status === Password::RESET_LINK_SENT;
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'message'    => $emailSent
                ? 'Employee created. A password setup link was sent to their email.'
                : 'Employee created, but the setup email could not be sent. Please resend it.',
            'email_sent' => $emailSent,
            'data'       => $employee->load('department', 'role'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
        return response()->json(['message' => 'Employee created (stub)']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, User $employee)
    {
        try {
            $employee->update($request->validated());
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Failed to update employee. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'Employee updated.',
            'data'    => $employee->load('department', 'role'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
        return response()->json(['message' => 'Employee created (stub)']);
    }

    public function managers(){
        try{
            $managers = User::where('role_id',2) -> get();

            return response()->json([
                
                'managers' => $managers
            ]);
        }catch(Exception $e){
            return response()->json(['message' => 'Failed to update employee. Please try again.'], 500);
        }
    }
}
