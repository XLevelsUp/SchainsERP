<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate username and password
        $request->validate([
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        // Check username in user_details table
        $user = AuthUser::where(
            'user_name',
            $request->user_name
        )->first();

        // Username not found
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        // Check entered password against password_hash
        if (!Hash::check(
            $request->password,
            $user->password_hash
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        // Login successful
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'user_name' => $user->user_name,
                'role_id' => $user->role_id
            ]
        ], 200);
    }
}