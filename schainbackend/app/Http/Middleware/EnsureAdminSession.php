<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes/admin.php after `auth:admin`: the request must come from an
 * admin account holding an admin-console token (the `admin` scope), and the
 * account must still be active. See App\Support\AdminAccess.
 */
class EnsureAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        // The scope is the second lock after the provider check: a token from
        // a client with no provider set would pass `auth:admin`, but it won't
        // carry this scope.
        if (!AdminAccess::isAdmin($admin) || !$admin->tokenCan(AdminAccess::SCOPE)) {
            return response()->json([
                'success' => false,
                'message' => 'This needs an admin console session. Sign in through the admin console.',
            ], 403);
        }

        // Deactivated since signing in: end the session rather than keep
        // honouring it. 401, so the console signs out.
        if (!AdminAccess::isEligible($admin)) {
            $admin->token()?->revoke();

            return response()->json([
                'success' => false,
                'message' => 'Your admin account has been deactivated. Please contact the system owner.',
            ], 401);
        }

        return $next($request);
    }
}
