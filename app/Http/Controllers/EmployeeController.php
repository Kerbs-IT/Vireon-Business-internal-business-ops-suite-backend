<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $status = in_array($request->query('status'), ['active', 'inactive'], true)
                ? $request->query('status')
                : null; // "all" or junk -> no filter

            // only a numeric id counts as a department filter ("all" or junk -> null)
            $departmentParam = $request->query('department');
            $departmentId = ctype_digit((string) $departmentParam) ? (int) $departmentParam : null;

            // manager: always their own department, whatever the client sent
            if ($user->role_id == 2) {
                $departmentId = $user->department_id;
            }

            $search = trim((string) $request->query('search', ''));

            $employees = User::with('department', 'role')
                ->where('role_id', '!=', 1)
                ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
                // grouped, so the ORs can't escape the conditions above
                ->when($search !== '', function ($q) use ($search) {
                    $term = '%' . addcslashes($search, '%_\\') . '%';

                    $q->where(function ($q) use ($term) {
                        $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term]);
                    });
                })
                ->when($status, fn($q) => $q->where('status', $status))
                ->get();

            return response()->json(['employees' => $employees]);
        } catch (\Throwable $e) {
            Log::error('employees index failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to load employees.'], 500);
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

    public function restore(Request $request, int $id)
    {
        try {
            $actor = $request->user();

            // allow-list: only admin and manager
            if (!in_array($actor->role_id, [1, 2], true)) {
                return response()->json(['message' => 'Not authorized.'], 403);
            }

            $employee = User::findOrFail($id);

            // admin accounts are not managed from this page
            if ($employee->role_id === 1) {
                return response()->json(['message' => 'Not authorized.'], 403);
            }

            // a manager can only reactivate people in their own department
            if ($actor->role_id === 2 && $employee->department_id !== $actor->department_id) {
                return response()->json(['message' => 'Not authorized.'], 403);
            }

            if ($employee->status === 'active') {
                return response()->json(['message' => 'This employee is already active.'], 422);
            }

            $employee->update(['status' => 'active']);

            return response()->json([
                'message' => 'Employee reactivated.',
                'data'    => $employee->load('department', 'role'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Employee not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('employee restore failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to reactivate the employee.'], 500);
        }
    }
}
