<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Admin;
use App\Services\AuthService;
use App\Services\SystemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin console sign-in (routes/admin.php). Admins are their own accounts
 * (table `admins`), not user_details rows, and their token works only on
 * /api/v1/admin routes. See App\Support\AdminAccess.
 */
class AdminAuthController extends Controller
{
    /** The fields the console needs; the two password hashes never leave. */
    public static function present(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'user_name' => $admin->user_name,
            'is_active' => $admin->is_active,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
            'password_changed_at' => $admin->password_changed_at?->toIso8601String(),
            'special_password_changed_at' => $admin->special_password_changed_at?->toIso8601String(),
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/login",
     *     summary="Admin console login",
     *     description="Authenticate an admin account (not a user_details account) and return a Passport Bearer token that works only on /api/v1/admin routes.",
     *     operationId="adminAuthLogin",
     *     tags={"Admin"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"user_name", "password"},
     *                 @OA\Property(property="user_name", type="string", example="admin"),
     *                 @OA\Property(property="password", type="string", example="secret")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful login: data.admin, data.token, data.token_type"),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="Admin account deactivated"),
     *     @OA\Response(response=429, description="Too many sign-in attempts")
     * )
     */
    public function login(LoginRequest $request, AuthService $auth): JsonResponse
    {
        $admin = $auth->verifyAdminCredentials($request->user_name, $request->password);
        $token = $auth->issueAdminToken($admin);

        $admin->forceFill(['last_login_at' => now()])->save();

        SystemLogService::log(
            'ADMIN',
            'ADMIN_LOGIN',
            null,
            $admin->id,
            'admins',
            "Admin {$admin->name} signed in to the admin console.",
            adminId: $admin->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'admin' => self::present($admin),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /** Revoke the admin token the request was made with. */
    public function logout(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $admin->token()?->revoke();

        SystemLogService::log(
            'ADMIN',
            'ADMIN_LOGOUT',
            null,
            $admin->id,
            'admins',
            "Admin {$admin->name} signed out of the admin console.",
            adminId: $admin->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ], 200);
    }

    /** The signed-in admin, for the console to verify its stored session on boot. */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated admin retrieved.',
            'data' => self::present($request->user()),
        ], 200);
    }
}
