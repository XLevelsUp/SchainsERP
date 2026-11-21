<?php

namespace App\Services;

use App\Models\CashHeadEmployeeMapping;
use App\Models\CustomerTouchUserMapping;
use App\Models\HeadEmployeeMapping;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Validation\ValidationException;

/**
 * Brings a user's mapping tables in line with what the admin console's
 * user form sends (2026-11-21). Called inside UserDetailController's
 * store / update transaction, so a refusal rolls the whole save back.
 *
 * Each sync replaces the user's set: rows not sent are removed, new ones
 * added, rows that stay are left as they are.
 */
class UserMappingSyncService
{
    /**
     * The heads a user reports to (stock or cash). $mappings is the request's
     * `head_mappings` / `cash_head_mappings`: [['head_id' => 3], ...]; null
     * (a multipart request can't carry an empty list) means none.
     *
     * @param class-string<HeadEmployeeMapping|CashHeadEmployeeMapping> $model
     */
    public function syncHeads(UserDetail $user, ?array $mappings, string $model): void
    {
        $wanted = collect($mappings ?? [])
            ->map(fn ($m) => (int) data_get($m, 'head_id'))
            ->filter()
            ->unique()
            ->values();

        $model::where('employee_id', $user->user_id)
            ->whereNotIn('head_id', $wanted->all())
            // A head listed under themselves belongs to the head side
            // (syncHeadUsers), so the employee side leaves that row alone.
            ->where('head_id', '!=', $user->user_id)
            ->delete();

        $existing = $model::where('employee_id', $user->user_id)->pluck('head_id')->map(fn ($id) => (int) $id);

        foreach ($wanted->diff($existing) as $headId) {
            $model::create([
                'head_id' => $headId,
                'employee_id' => $user->user_id,
                'added_at' => now(),
            ]);
        }
    }

    /**
     * For a head: the users under them (the HEAD form's "Head Users") —
     * head_employee_mappings rows where this user is the head. These are the
     * users the head works with in the app's pickers. $mappings:
     * [['employee_id' => 7], ...]; null means none. The head may list
     * themselves, as the legacy form does.
     */
    public function syncHeadUsers(UserDetail $head, ?array $mappings): void
    {
        $wanted = collect($mappings ?? [])
            ->map(fn ($m) => (int) data_get($m, 'employee_id'))
            ->filter()
            ->unique()
            ->values();

        HeadEmployeeMapping::where('head_id', $head->user_id)
            ->whereNotIn('employee_id', $wanted->all())
            ->delete();

        $existing = HeadEmployeeMapping::where('head_id', $head->user_id)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id);

        foreach ($wanted->diff($existing) as $employeeId) {
            HeadEmployeeMapping::create([
                'head_id' => $head->user_id,
                'employee_id' => $employeeId,
                'added_at' => now(),
            ]);
        }
    }

    /**
     * The customer touches a user is given. $mappings:
     * [['customer_touch_id' => 2, 'is_active' => true], ...]; null means none.
     */
    public function syncCustomerTouches(UserDetail $user, ?array $mappings): void
    {
        $wanted = collect($mappings ?? [])
            ->mapWithKeys(fn ($m) => [
                (int) data_get($m, 'customer_touch_id') => filter_var(data_get($m, 'is_active', true), FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($active, $touchId) => $touchId > 0);

        CustomerTouchUserMapping::where('user_id', $user->user_id)
            ->whereNotIn('customer_touch_id', $wanted->keys()->all())
            ->delete();

        $existing = CustomerTouchUserMapping::where('user_id', $user->user_id)->get()->keyBy('customer_touch_id');

        foreach ($wanted as $touchId => $active) {
            if ($row = $existing->get($touchId)) {
                if ((bool) $row->is_active !== $active) {
                    $row->update(['is_active' => $active, 'updated_at' => now()]);
                }
                continue;
            }

            CustomerTouchUserMapping::create([
                'user_id' => $user->user_id,
                'customer_touch_id' => $touchId,
                'is_active' => $active,
                'added_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * An admin's edit of a user's assigned items. Admins choose which items a
     * user deals in and which is primary; the gram / purity totals are
     * balances the transactions keep, so they're never taken from the
     * request:
     *  - an item that stays keeps its totals (only is_primary can change);
     *  - a new item starts at zero;
     *  - an item still holding a balance can't be removed (422).
     *
     * $mappings: [['item_id' => 2, 'is_primary' => 1], ...].
     */
    public function syncItemsKeepingTotals(UserDetail $user, array $mappings): void
    {
        $wanted = collect($mappings)
            ->mapWithKeys(fn ($m) => [(int) data_get($m, 'item_id') => (int) (bool) data_get($m, 'is_primary', 0)])
            ->filter(fn ($primary, $itemId) => $itemId > 0);

        $existing = UsersItemsMapping::with('item:item_id,item_name')
            ->where('user_id', $user->user_id)
            ->get()
            ->keyBy('item_id');

        $holdingBalance = $existing
            ->reject(fn ($row, $itemId) => $wanted->has($itemId))
            ->filter(fn ($row) => bccomp((string) ($row->item_grams_total ?? '0'), '0', 4) !== 0
                || bccomp((string) ($row->item_purity_total ?? '0'), '0', 4) !== 0);

        if ($holdingBalance->isNotEmpty()) {
            $names = $holdingBalance->map(fn ($row) => $row->item?->item_name ?? "#{$row->item_id}")->implode(', ');

            throw ValidationException::withMessages([
                'item_mappings' => ["{$names} still holds a balance for this user, so it can't be removed."],
            ]);
        }

        UsersItemsMapping::where('user_id', $user->user_id)
            ->whereNotIn('item_id', $wanted->keys()->all())
            ->delete();

        foreach ($wanted as $itemId => $primary) {
            if ($row = $existing->get($itemId)) {
                if ((int) $row->is_primary !== $primary) {
                    UsersItemsMapping::whereKey($row->getKey())->update(['is_primary' => $primary]);
                }
                continue;
            }

            UsersItemsMapping::create([
                'item_id' => $itemId,
                'user_id' => $user->user_id,
                'item_grams_total' => '0',
                'item_purity_total' => '0',
                'is_primary' => $primary,
                'added_at' => now(),
            ]);
        }
    }
}
