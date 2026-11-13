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
        return bcadd($this->num($a), $this->num($b), 4);
    }

    /**
     * Perform BCMath subtraction with 4 decimal places.
     */
    protected function sub($a, $b): string
    {
        return bcsub($this->num($a), $this->num($b), 4);
    }

    /**
     * Perform BCMath multiplication with 4 decimal places.
     */
    protected function mul($a, $b): string
    {
        return bcmul($this->num($a), $this->num($b), 4);
    }

    /**
     * Perform BCMath division with 4 decimal places.
     */
    protected function div($a, $b): string
    {
        if (bccomp($this->num($b), '0', 4) === 0) {
            return '0.0000';
        }
        return bcdiv($this->num($a), $this->num($b), 4);
    }

    /**
     * A number as a bcmath-safe string. The services subtract by prefixing a
     * minus sign ('-' . $grams); when the value is itself negative (a gold
     * correction's reversal, GoldCorrectionService) that gives "--1.0000",
     * which bcmath rejects. Collapse the run of signs: an even number of
     * minuses is positive, an odd number negative.
     */
    protected function num($value): string
    {
        $value = trim((string) ($value ?? 0));
        if (!preg_match('/^([+-]+)(.*)$/', $value, $m)) {
            return $value === '' ? '0' : $value;
        }
        $negative = substr_count($m[1], '-') % 2 === 1;

        return ($negative ? '-' : '') . ($m[2] === '' ? '0' : $m[2]);
    }

    /**
     * Opening and closing snapshot columns for a cash-on-hand move of $amount
     * from $sender to $recipient, read from the (locked) balances before the
     * move. Cash moves; RTGS doesn't, so its closing equals its opening.
     * Used by the stock services' cash entries (numeric wastage, Stock screen
     * cash out), which used to leave every column but the opening cash empty,
     * so the Cash Transactions report showed no OB/CB for them.
     */
    protected function cashOnHandSnapshots(UserDetail $sender, UserDetail $recipient, $amount): array
    {
        $senderCash = $this->num($sender->rak_cash_balance);
        $recipientCash = $this->num($recipient->rak_cash_balance);
        $senderRtgs = $this->num($sender->rak_rtgs_balance);
        $recipientRtgs = $this->num($recipient->rak_rtgs_balance);

        return [
            'sender_opening_cash' => $senderCash,
            'sender_opening_rtgs' => $senderRtgs,
            'recipient_opening_cash' => $recipientCash,
            'recipient_opening_rtgs' => $recipientRtgs,
            'sender_closing_cash' => $this->sub($senderCash, $amount),
            'sender_closing_rtgs' => $senderRtgs,
            'recipient_closing_cash' => $this->add($recipientCash, $amount),
            'recipient_closing_rtgs' => $recipientRtgs,
        ];
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
