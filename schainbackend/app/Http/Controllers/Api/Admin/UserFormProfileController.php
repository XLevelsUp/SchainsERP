<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\UserFormProfiles;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/admin/user-form-profiles — which sections and permissions the
 * user form shows for each role (App\Support\UserFormProfiles). The console
 * draws the form from this; the same profiles are enforced on save.
 */
class UserFormProfileController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User form profiles retrieved.',
            'data' => UserFormProfiles::catalog(),
        ], 200);
    }
}
