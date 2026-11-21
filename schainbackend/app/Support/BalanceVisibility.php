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
 * Admin console accounts see balances too, read-only (2026-11-21): the
 * legacy admin user form shows each user's item totals, grand totals and
 * cash / RTGS balance greyed out. Seeing is all — viewerIsHead() stays false
 * for them, so the head-only writes (changing item totals) still refuse.
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
            && !AdminAccess::isAdmin($viewer)
            && strtoupper((string) optional($viewer->role)->role) === 'HEAD';
    }

    public static function viewerIsAdmin(): bool
    {
        return AdminAccess::isAdmin(self::viewer());
    }

    public static function canSeeBalancesOf($userId): bool
    {
        $viewer = self::viewer();

        if ($viewer === null || self::viewerIsAdmin()) {
            return true;
        }

        if ($userId !== null && (int) $userId === (int) $viewer->user_id) {
            return true;
        }

        return self::viewerIsHead();
    }
}
