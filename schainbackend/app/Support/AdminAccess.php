<?php

namespace App\Support;

use App\Models\Admin;

/**
 * The admin console's access rule, in one place.
 *
 * An admin is NOT a head. Admins are their own accounts (table `admins`,
 * App\Models\Admin) whose job is creating users and assigning them to
 * heads. Heads keep running the business in the app: stock, cash, items,
 * roles, banks, settings and the audit log.
 *
 * Two independent checks keep the realms apart:
 *  - Provider: admin tokens come from a personal access client pinned to
 *    the `admins` provider, so Passport refuses them on the `api` guard,
 *    and app tokens on the `admin` guard (config/auth.php).
 *  - Scope: admin tokens carry the `admin` scope and EnsureAdminSession
 *    requires it, so even a token from a provider-less client can't open
 *    the console.
 *
 * Sensitive changes also need the admin's special password on the request
 * itself (RequireSpecialPassword).
 */
final class AdminAccess
{
    /** Passport scope carried by admin-console tokens. */
    public const SCOPE = 'admin';

    /** Name stored on admin tokens in oauth_access_tokens. */
    public const TOKEN_NAME = 'admin_token';

    /** Request header carrying the special password. */
    public const SPECIAL_PASSWORD_HEADER = 'X-Special-Password';

    public static function isAdmin(mixed $user): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Whether this admin may hold a session right now. Checked at sign-in and
     * on every admin request, so deactivating an admin ends an open session.
     */
    public static function isEligible(?Admin $admin): bool
    {
        return $admin !== null && $admin->is_active;
    }
}
