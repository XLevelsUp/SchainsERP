<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

/**
 * Rules for an admin's two secrets, shared by the account endpoints
 * (Admin\AdminAccountController) and the artisan commands.
 *
 * The special password is a second factor the admin types again for
 * sensitive changes. It must differ from the login password, or it would
 * add nothing.
 */
final class AdminPasswordPolicy
{
    public const PASSWORD_MIN = 8;
    public const SPECIAL_PASSWORD_MIN = 6;

    /** Validation rules for a new login password (with `_confirmation`). */
    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:' . self::PASSWORD_MIN, 'max:255', 'confirmed'];
    }

    /** Validation rules for a new special password (with `_confirmation`). */
    public static function specialPasswordRules(): array
    {
        return ['required', 'string', 'min:' . self::SPECIAL_PASSWORD_MIN, 'max:255', 'confirmed'];
    }

    public static function checkPassword(Admin $admin, string $plain): bool
    {
        return Hash::check($plain, $admin->password_hash);
    }

    public static function checkSpecialPassword(Admin $admin, string $plain): bool
    {
        return $plain !== '' && Hash::check($plain, $admin->special_password_hash);
    }

    public static function setPassword(Admin $admin, string $plain): void
    {
        $admin->forceFill([
            'password_hash' => Hash::make($plain),
            'password_changed_at' => now(),
        ])->save();
    }

    public static function setSpecialPassword(Admin $admin, string $plain): void
    {
        $admin->forceFill([
            'special_password_hash' => Hash::make($plain),
            'special_password_changed_at' => now(),
        ])->save();
    }
}
