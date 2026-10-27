<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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
}
