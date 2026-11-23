<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin console: switch a role on or off (agreed 2026-11-23). Nothing else
 * about a role changes here; names stay create-only.
 *
 * Switching a role off also locks out every user who holds it: the app's
 * sign-in refuses them (AuthService) and a session they already hold ends
 * on its next request (EnsureAppSession). Switching it back on lets them in
 * again; their own account status is untouched either way.
 *
 * Needs the special password (routes/admin.php) and is audited as
 * ROLE_STATUS_CHANGE (LogAdminAction).
 */
class RoleStatusController extends Controller
{
    // PUT|PATCH: /api/v1/admin/roles/{role}/status
    public function update(Request $request, int $role): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $model = Role::find($role);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $model->update(['is_active' => (bool) $validated['is_active']]);

        // How many users this reaches, so the console can say so.
        $usersCount = UserDetail::where('role_id', $model->id)->count();

        return response()->json([
            'success' => true,
            'message' => $model->is_active
                ? "Role {$model->role} activated."
                : "Role {$model->role} deactivated.",
            'data' => [
                'id' => $model->id,
                'role' => $model->role,
                'is_active' => $model->is_active,
                'users_count' => $usersCount,
            ],
        ], 200);
    }
}
