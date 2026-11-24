<?php

namespace App\Support;

use App\Models\PhoneBook;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;

/**
 * Adds a user created in the admin console to the phone book (agreed
 * 2026-11-24). A number already in the phone book isn't added twice: an
 * unlinked contact with it is linked to the user, and its name, role and
 * status are left as they are.
 *
 * Runs inside the user's own transaction behind a savepoint, so a phone
 * book problem is logged and never undoes the user.
 */
final class PhoneBookSync
{
    public static function addUser(UserDetail $user): void
    {
        $phone = trim((string) $user->phone_no);
        if ($phone === '') {
            return;
        }

        try {
            DB::transaction(function () use ($user, $phone) {
                $contact = PhoneBook::where('phone_no', $phone)->lockForUpdate()->first();

                if ($contact === null) {
                    // user_details.role_id is text.
                    $roleId = is_numeric($user->role_id) ? (int) $user->role_id : null;
                    PhoneBook::create([
                        'name' => mb_substr((string) $user->name, 0, 55),
                        'phone_no' => $phone,
                        'role_id' => $roleId !== null && Role::whereKey($roleId)->exists() ? $roleId : null,
                        'is_active' => true,
                        'user_id' => $user->user_id,
                    ]);
                } elseif ($contact->user_id === null) {
                    $contact->update(['user_id' => $user->user_id]);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
