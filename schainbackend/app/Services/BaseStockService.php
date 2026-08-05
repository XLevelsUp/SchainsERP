<?php

namespace App\Services;

use App\Models\UserDetail;
use App\Models\UsersItemsMapping;

abstract class BaseStockService
{
    /**
     * Perform BCMath addition with 4 decimal places.
     */
    protected function add($a, $b): string
    {
        return bcadd((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath subtraction with 4 decimal places.
     */
    protected function sub($a, $b): string
    {
        return bcsub((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath multiplication with 4 decimal places.
     */
    protected function mul($a, $b): string
    {
        return bcmul((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath division with 4 decimal places.
     */
    protected function div($a, $b): string
    {
        if (bccomp((string)($b ?? 0), '0', 4) === 0) {
            return '0.0000';
        }
        return bcdiv((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Helper to load/update user item balances in users_items_mappings.
     */
    protected function updateUserItemBalance(int $userId, int $itemId, string $gramsChange, string $purityChange): void
    {
        $hasExistingMapping = UsersItemsMapping::where('user_id', $userId)->exists();
        $isPrimary = $hasExistingMapping ? 0 : 1;

        $mapping = UsersItemsMapping::firstOrCreate(
            ['user_id' => $userId, 'item_id' => $itemId],
            ['item_grams_total' => '0.0000', 'item_purity_total' => '0.0000', 'is_primary' => $isPrimary]
        );

        $mapping->item_grams_total = $this->add($mapping->item_grams_total, $gramsChange);
        $mapping->item_purity_total = $this->add($mapping->item_purity_total, $purityChange);
        $mapping->last_txn_date = now();
        $mapping->save();
    }

    /**
     * Build JSON snapshots of user & item balances.
     */
    protected function buildUserItemDetails(int $userId, int $itemId, ?int $toItemId, string $type): array
    {
        $user = UserDetail::findOrFail($userId);
        
        $itemMapping = UsersItemsMapping::where('user_id', $userId)
            ->where('item_id', $itemId)
            ->first();

        $toItemMapping = null;
        if ($toItemId) {
            $toItemMapping = UsersItemsMapping::where('user_id', $userId)
                ->where('item_id', $toItemId)
                ->first();
        }

        return [
            'type' => strtoupper($type),
            'date_time' => now()->toDateTimeString(),
            'user_details' => [
                'user_id' => $user->user_id,
                'role_id' => $user->role_id,
                'grams_grand_total' => (string)($user->grams_grand_total ?? 0),
                'purity_grand_total' => (string)($user->purity_grand_total ?? 0),
            ],
            'item_details' => [
                'item_id' => $itemId,
                'ob_grams' => (string)($itemMapping->item_grams_total ?? 0),
                'ob_purity' => (string)($itemMapping->item_purity_total ?? 0),
                'cb_grams' => (string)($itemMapping->item_grams_total ?? 0),
                'cb_purity' => (string)($itemMapping->item_purity_total ?? 0),
            ],
            'to_item_details' => $toItemId ? [
                'item_id' => $toItemId,
                'ob_grams' => (string)($toItemMapping->item_grams_total ?? 0),
                'ob_purity' => (string)($toItemMapping->item_purity_total ?? 0),
                'cb_grams' => (string)($toItemMapping->item_grams_total ?? 0),
                'cb_purity' => (string)($toItemMapping->item_purity_total ?? 0),
            ] : null,
        ];
    }
}
