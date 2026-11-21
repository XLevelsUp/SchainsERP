<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminSpecialPasswordRequest;
use App\Models\Admin;
use App\Support\AdminPasswordPolicy;
use Illuminate\Http\JsonResponse;

/**
 * The signed-in admin's own account: changing the login password and the
 * special password. Each change needs the current value of the secret being
 * changed (validated in the FormRequests), and the two must stay different.
 */
class AdminAccountController extends Controller
{
    public function updatePassword(UpdateAdminPasswordRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        AdminPasswordPolicy::setPassword($admin, $request->input('password'));

        return response()->json([
            'success' => true,
            'message' => 'Password changed.',
            'data' => AdminAuthController::present($admin->fresh()),
        ], 200);
    }

    public function updateSpecialPassword(UpdateAdminSpecialPasswordRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        AdminPasswordPolicy::setSpecialPassword($admin, $request->input('special_password'));

        return response()->json([
            'success' => true,
            'message' => 'Special password changed.',
            'data' => AdminAuthController::present($admin->fresh()),
        ], 200);
    }
}
