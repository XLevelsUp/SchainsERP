<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who may read whose books (agreed 2026-11-12).
 *
 * - A HEAD may read anyone's stock and cash records, balances included.
 * - A user with the "can act as head" permission (`is_act_as_head_enabled`)
 *   may read the records they need to enter values for any head: history,
 *   lots, stock summary, entries. Balances stay hidden from them
 *   (App\Support\BalanceVisibility), and the balance reports (Items OB/CB,
 *   Consolidated) stay HEAD-only for other users.
 * - Everyone else (EMPLOYEE, CUSTOMER, PURCHASE without the permission) may
 *   read only their own records.
 *
 * The acting user always comes from the access token, never from an
 * `X-User-ID` header or a query-string id.
 */
trait ScopesRecordAccess
{
    protected function actingUserId(Request $request): int
    {
        return (int) $request->user()->user_id;
    }

    protected function actingUserIsHead(Request $request): bool
    {
        return strtoupper((string) optional($request->user()->role)->role) === 'HEAD';
    }

    protected function actingUserCanActAsHead(Request $request): bool
    {
        return $this->actingUserIsHead($request)
            || (bool) $request->user()->is_act_as_head_enabled;
    }

    /**
     * For a user who can't act as head, every listed parameter that names a
     * user must name the acting user. Accepts plain ids and the `user_<id>` /
     * `retailer_<id>` forms some reports use. Returns a 403 response to send,
     * or null if the request is allowed.
     */
    protected function forbidOtherUsersRecords(Request $request, array $params): ?JsonResponse
    {
        if ($this->actingUserCanActAsHead($request)) {
            return null;
        }

        return $this->forbidUnlessSelf($request, $params);
    }

    /**
     * Balance reports: only a HEAD may run them for someone else.
     */
    protected function forbidOtherUsersBalanceReport(Request $request, array $params): ?JsonResponse
    {
        if ($this->actingUserIsHead($request)) {
            return null;
        }

        return $this->forbidUnlessSelf($request, $params);
    }

    /**
     * For a user who can't act as head, the record must have the acting user
     * as one of its parties. Returns a 403 response to send, or null.
     */
    protected function forbidUnlessParty(Request $request, ...$partyIds): ?JsonResponse
    {
        if ($this->actingUserCanActAsHead($request)) {
            return null;
        }

        $self = $this->actingUserId($request);

        foreach ($partyIds as $partyId) {
            if ($partyId !== null && (int) $partyId === $self) {
                return null;
            }
        }

        return $this->forbiddenRecordsResponse();
    }

    private function forbidUnlessSelf(Request $request, array $params): ?JsonResponse
    {
        $self = $this->actingUserId($request);

        foreach ($params as $param) {
            $value = $request->input($param);
            if ($value === null || $value === '') {
                continue;
            }

            $id = preg_replace('/^(user_|retailer_)/', '', (string) $value);
            if (!ctype_digit($id) || (int) $id !== $self) {
                return $this->forbiddenRecordsResponse();
            }
        }

        return null;
    }

    protected function forbiddenRecordsResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You can only view your own records.',
        ], 403);
    }
}
