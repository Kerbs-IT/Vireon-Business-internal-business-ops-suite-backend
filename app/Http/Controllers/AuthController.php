<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    //

    public function login(Request $request){

        $credential = $request -> validate([
            'email' => ['required','email'],
            'password' => ['required']
        ]);

        $user = User::where('email',$credential['email'])->first();

        if(! $user || ! Hash::check($credential['password'],$user->password)){
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // create a token for the session
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('role', 'department'),
            'token' => $token
        ]);
    }


    public function logout(Request $request){
        $request->user()->currentAccessToken() -> delete();

        return response() -> json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request){


        return response() -> json($request->user()->load('role','department'));
    }

    public function deactivate($id)
    {
        try {
            $user = Auth::user();

            if ($user->role_id == 3) {
                return response()->json(['message' => 'You are not authorized to deactivate accounts.'], 403);
            }

            $employee = User::findOrFail($id);

            if ($user->role_id == 2 && $employee->department_id != $user->department_id) {
                return response()->json([
                    'message' => 'Unable to deactivate the account, this employee is not under your department.',
                ], 403);
            }

            $employee->update(['status' => 'inactive']);

            return response()->json(['message' => 'Employee account deactivated successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Something went wrong.'], 500);
        }
    }
}
