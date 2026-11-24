<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use App\Services\OpeningBalanceService;
use App\Support\UserFormProfiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * POST /admin/user-details/{id}/opening-balance (agreed 2026-11-24): the
 * user form's opening balance modal on an existing user. Needs the special
 * password (routes/admin.php). A new user's opening balances go with its
 * create request instead (UserDetailController::store, opening_balances).
 */
class OpeningBalanceController extends Controller
{
    public function store(Request $request, $userDetail, OpeningBalanceService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), OpeningBalanceService::rules(), OpeningBalanceService::messages());
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $entry = $validator->validated();

        if (!UserDetail::whereKey($userDetail)->exists()) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            $result = DB::transaction(function () use ($userDetail, $entry, $service, $request) {
                $user = UserDetail::whereKey($userDetail)->lockForUpdate()->firstOrFail();
                OpeningBalanceService::assertAllowed(UserFormProfiles::forRoleId($user->role_id), $entry['field'], 'field');

                $update = $service->apply($user, $entry, $request->user()->getKey());

                return [
                    'update' => $update->fresh(),
                    'balances' => OpeningBalanceService::balancesOf($user, $entry['item_id'] ?? null),
                ];
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to update the balance.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Balance updated.', 'data' => $result], 200);
    }
}
