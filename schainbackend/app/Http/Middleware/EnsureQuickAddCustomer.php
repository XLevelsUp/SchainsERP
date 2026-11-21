<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/user-details in the app (agreed 2026-11-20): creating users
 * and assigning them to heads belongs to the admin console. The one creation
 * left in the app is "Add Retailer" on the Stock screen, which adds a
 * customer mapped to whoever is entering the stock. This route accepts that
 * and nothing more:
 *
 *  - the role must be CUSTOMER;
 *  - head_mappings may only name the signed-in user;
 *  - no cash head mappings, and no "Can act as head" permission.
 *
 * Runs after act.as.head, so the caller is a head or has that permission.
 */
class EnsureQuickAddCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerRoleIds = Role::whereRaw('UPPER(role) = ?', ['CUSTOMER'])
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (!in_array((string) $request->input('role_id'), $customerRoleIds, true)) {
            return $this->refuse('Only customers can be added here. Ask an admin to create other users.');
        }

        $selfId = (int) $request->user()->user_id;
        $headIds = collect($request->input('head_mappings', []))
            ->map(fn ($mapping) => (int) data_get($mapping, 'head_id'));

        if ($headIds->contains(fn (int $id) => $id !== $selfId)) {
            return $this->refuse('A customer added here can only be assigned to you. Ask an admin to assign other heads.');
        }

        if ($request->filled('cash_head_mappings') || $request->boolean('is_act_as_head_enabled')) {
            return $this->refuse('Cash heads and permissions are set by an admin.');
        }

        return $next($request);
    }

    private function refuse(string $message): Response
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }
}
