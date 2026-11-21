<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PATCH /api/v1/admin/user-details/{user_detail}/status — activate or
 * deactivate a user from the Users list (the legacy list's editable Is
 * Active column), without sending the whole user form. A deactivated user
 * can't sign in to the app (AuthService).
 */
class UserStatusController extends Controller
{
    public function update(Request $request, int $userDetail): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user = UserDetail::where('user_id', $userDetail)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->update([
            'is_active' => (bool) $validated['is_active'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'User activated.' : 'User deactivated.',
            'data' => [
                'user_id' => $user->user_id,
                'is_active' => $user->is_active,
            ],
        ], 200);
    }
}
