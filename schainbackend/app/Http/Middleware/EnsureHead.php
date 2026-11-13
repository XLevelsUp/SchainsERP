<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration (agreed 2026-11-12, BACKEND_TODO §26): only a HEAD may
 * change users, roles, items, settings, banks, cash categories, customer
 * touch and the mapping tables. Reading stays open for the screens' pickers.
 */
class EnsureHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || strtoupper((string) optional($user->role)->role) !== 'HEAD') {
            return response()->json([
                'success' => false,
                'message' => 'Only a head can make this change.',
            ], 403);
        }

        return $next($request);
    }
}
