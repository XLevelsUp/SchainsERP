<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app shows a signed-in user only what their permissions allow
 * (agreed 2026-11-21): an admin switches features on per user in the admin
 * console's user form. The screens hide what's off; this refuses the
 * feature's own endpoint too, so it can't be reached around the screen.
 *
 *   user.permission:is_item_change_need_shown
 *
 * Only endpoints that belong to one feature are gated this way. Shared ones
 * (e.g. cash in/out, used by the Stock screen and Cash Management alike)
 * are left to the screens.
 */
class EnsureUserPermission
{
    /** Each permission this guards, and what it opens (for the message). */
    private const FEATURES = [
        'is_item_change_need_shown' => 'Item Change',
        'is_item_conversion_need_shown' => 'Item Conversion',
        'is_cash_mngmt_need_to_shown' => 'cash entries on the Stock screen',
        'is_phone_book_need_shown' => 'the Phone Book',
        'is_create_order_shown' => 'creating orders',
    ];

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!array_key_exists($permission, self::FEATURES)) {
            throw new LogicException("user.permission: unknown permission [{$permission}].");
        }

        $user = $request->user();

        if (!$user || !(bool) $user->{$permission}) {
            return response()->json([
                'success' => false,
                'message' => 'Your account doesn\'t have access to ' . self::FEATURES[$permission] . '. Ask an admin to switch it on.',
            ], 403);
        }

        return $next($request);
    }
}
