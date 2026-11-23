<?php

namespace App\Http\Middleware;

use App\Models\AuthUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the app's routes after `auth:api`: a user whose role has been
 * switched off in the admin console (Roles page, agreed 2026-11-23) loses
 * the session they already hold. Their token is revoked and the request is
 * a 401, so the app signs them out; sign-in then refuses them until the
 * role is switched back on (AuthService::verifyCredentials).
 *
 * The admin console's counterpart is EnsureAdminSession.
 */
class EnsureAppSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof AuthUser && $user->hasInactiveRole()) {
            $user->token()?->revoke();

            return response()->json([
                'success' => false,
                'message' => 'Your role has been deactivated. Please contact your administrator.',
            ], 401);
        }

        return $next($request);
    }
}
