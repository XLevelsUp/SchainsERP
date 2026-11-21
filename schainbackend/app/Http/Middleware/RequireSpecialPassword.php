<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use App\Support\AdminPasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sensitive admin changes need the admin's special password, sent with the
 * request in the X-Special-Password header (agreed 2026-11-20: assigning or
 * changing a user's heads, and deleting a user).
 *
 *   special.password                                  always required
 *   special.password:head_mappings,cash_head_mappings  required only when one
 *                                                      of these inputs is sent
 *
 * "Sent" includes an empty list: on an edit, `head_mappings: []` removes
 * every head, which is as sensitive as adding one. The console sends the
 * head lists only when they changed.
 *
 * A missing or wrong password is a 422 on `special_password`, so the console
 * shows it on the prompt's field. Five wrong tries lock the check for five
 * minutes (429). Runs after EnsureAdminSession, so there is always an admin.
 */
class RequireSpecialPassword
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public function handle(Request $request, Closure $next, string ...$whenSent): Response
    {
        if ($whenSent !== [] && !collect($whenSent)->contains(fn (string $field) => $request->exists($field))) {
            return $next($request);
        }

        $admin = $request->user();
        $key = 'admin-special-password:' . $admin->getKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => "Too many incorrect special password attempts. Try again in {$seconds} seconds.",
            ], 429, ['Retry-After' => $seconds]);
        }

        $given = (string) $request->header(AdminAccess::SPECIAL_PASSWORD_HEADER, '');

        if ($given === '') {
            return $this->invalid('This change needs your special password.', 'Enter your special password to make this change.');
        }

        if (!AdminPasswordPolicy::checkSpecialPassword($admin, $given)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            return $this->invalid('The special password is incorrect.', 'The special password is incorrect.');
        }

        RateLimiter::clear($key);
        // Read by LogAdminAction, so the audit entry records it.
        $request->attributes->set('special_password_verified', true);

        return $next($request);
    }

    private function invalid(string $message, string $fieldMessage): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['special_password' => [$fieldMessage]],
        ], 422);
    }
}
