<?php

namespace App\Services;

use App\Exceptions\LoginRejectedException;
use App\Models\Admin;
use App\Models\AuthUser;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Hash;

/**
 * Credential checks and token issuing for the app sign-in (AuthController)
 * and the admin console sign-in (Admin\AdminAuthController).
 */
class AuthService
{
    /**
     * App sign-in: a user_details account. Returns it with its role loaded;
     * throws LoginRejectedException (401 / 403) otherwise.
     */
    public function verifyCredentials(string $userName, string $password): AuthUser
    {
        $user = AuthUser::where('user_name', $userName)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw LoginRejectedException::invalidCredentials();
        }

        if (!$user->is_active) {
            throw LoginRejectedException::deactivated();
        }

        // A role switched off in the admin console locks out everyone on it
        // until it is switched back on (agreed 2026-11-23).
        $user->load('role');
        if ($user->hasInactiveRole()) {
            throw LoginRejectedException::roleDeactivated();
        }

        return $user;
    }

    /**
     * Admin console sign-in: an `admins` account. A user_details account,
     * even a head's, never gets in — it isn't in this table.
     */
    public function verifyAdminCredentials(string $userName, string $password): Admin
    {
        $admin = Admin::where('user_name', $userName)->first();

        if (!$admin || !Hash::check($password, $admin->password_hash)) {
            throw LoginRejectedException::invalidCredentials();
        }

        if (!AdminAccess::isEligible($admin)) {
            throw LoginRejectedException::deactivated();
        }

        return $admin;
    }

    /** A token for the operational app: no scopes. */
    public function issueAppToken(AuthUser $user): string
    {
        return $user->createToken('auth_token')->accessToken;
    }

    /** A token for the admin console: `admins` provider, `admin` scope. */
    public function issueAdminToken(Admin $admin): string
    {
        return $admin->createToken(AdminAccess::TOKEN_NAME, [AdminAccess::SCOPE])->accessToken;
    }
}
