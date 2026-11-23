<?php

namespace App\Support;

use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Support\Collection;

/**
 * The users who hold the HEAD role, found by role name (ids differ between
 * databases). Used where only a head may be chosen (agreed 2026-11-23): the
 * admin console's Head lists, Cash heads and a head's Head users.
 */
class HeadUsers
{
    /** @return Collection<int, int> user ids */
    public static function ids(): Collection
    {
        // user_details.role_id is text, roles.id an integer: compare as text.
        $roleIds = Role::whereRaw('UPPER(role) = ?', ['HEAD'])
            ->pluck('id')
            ->map(fn ($id) => (string) $id);

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return UserDetail::whereIn('role_id', $roleIds->all())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }
}
