<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Who may see balances (agreed 2026-11-12).
 *
 * A HEAD sees everyone's balances. Anyone else, including a user with the
 * "can act as head" permission, sees only their own: cash and RTGS balances,
 * gram/purity totals, item totals and opening/closing snapshots of other
 * users are left out of what they receive.
 *
 * Code running without a signed-in user (console commands, seeders, queued
 * jobs) is unaffected.
 */
final class BalanceVisibility
{
    public static function viewer()
    {
        return Auth::user();
    }

    public static function viewerIsHead(): bool
    {
        $viewer = self::viewer();

        return $viewer !== null
            && strtoupper((string) optional($viewer->role)->role) === 'HEAD';
    }

    public static function canSeeBalancesOf($userId): bool
    {
        $viewer = self::viewer();

        if ($viewer === null) {
            return true;
        }

        if ($userId !== null && (int) $userId === (int) $viewer->user_id) {
            return true;
        }

        return self::viewerIsHead();
    }
}
