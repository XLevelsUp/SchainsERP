<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock and cash entries (agreed 2026-11-12): only a HEAD, or a user given
 * the "can act as head" permission (`is_act_as_head_enabled`), may enter
 * values. They may enter them for any head.
 */
class EnsureCanActAsHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isHead = $user && strtoupper((string) optional($user->role)->role) === 'HEAD';
        $mayAct = $user && (bool) $user->is_act_as_head_enabled;

        if (!$isHead && !$mayAct) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to enter stock or cash transactions.',
            ], 403);
        }

        return $next($request);
    }
}
