<?php

namespace App\Services;

use App\Models\BankDetail;
use App\Models\CashTxnDetail;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Editing cash entries (BACKEND_TODO §17, agreed 2026-11-12): heads only, no
 * deletes, and an edit must correct everything the entry affected.
 *
 * Covered so far:
 *
 * Phase 1: plain Cash In / Cash Out (types INCOME and EXPENSE that are not
 * tied to a gold transaction or a numeric wastage entry). Those moved money
 * the same way in CashTxnDetailService::storeIncome / storeExpense and
 * StockOutService::createCashOut:
 *
 *   CASH_ON_HAND  sender cash  -amount, recipient cash  +amount
 *   otherwise     sender RTGS  -amount, recipient RTGS  +amount, and for
 *                 BANK the bank ledger +amount (INCOME) or -amount (EXPENSE)
 *
 * Phase 2: Cash Auto Entry (type AUTO_ENTRY). CashAutoEntryService only
 * takes cash from the sender (legacy behaviour) and is always cash on hand:
 *
 *   sender cash -amount, recipient untouched; payment method can't change
 *
 * An edit works out the difference between the old and the new effect for
 * each party and balance (cash, RTGS), then:
 *   1. applies it to both parties' balances and to the bank(s),
 *   2. shifts this entry's closing snapshots by it,
 *   3. shifts the opening and closing snapshots of every later entry
 *      (higher txn_id) of those two parties by it, so the frozen running
 *      balances the reports read stay correct.
 *
 * The parties themselves can't be changed.
 */
class CashTxnEditService
{
    private const EDITABLE_TYPES = ['INCOME', 'EXPENSE', 'AUTO_ENTRY'];

    /** Why this entry can't be edited yet, or null when it can. */
    public static function notEditableReason(CashTxnDetail $txn): ?string
    {
        if (!in_array($txn->type, self::EDITABLE_TYPES, true)) {
            return 'Only Cash In, Cash Out and Cash Auto Entry entries can be edited for now.';
        }
        if (!empty($txn->cash_to_gold_id)) {
            return "This entry belongs to gold entry #{$txn->cash_to_gold_id}; correct the gold entry instead.";
        }
        if (str_starts_with((string) $txn->remarks, 'NUMERIC_WASTAGE')) {
            return 'This entry belongs to a numeric wastage entry; correct the numeric wastage entry instead.';
        }

        return null;
    }

    /**
     * @param array $data Any of: amount, payment_method, bank_account_id,
     *                    category_id, remarks, created_at.
     */
    public function edit(int $txnId, array $data, int $actorId): CashTxnDetail
    {
        return DB::transaction(function () use ($txnId, $data, $actorId) {
            $txn = CashTxnDetail::where('txn_id', $txnId)->lockForUpdate()->firstOrFail();

            if ($reason = self::notEditableReason($txn)) {
                throw new InvalidArgumentException($reason);
            }

            // Lock both parties in a fixed order so two edits can't deadlock.
            $users = UserDetail::whereIn('user_id', [$txn->sender_id, $txn->recipient_id])
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');
            $sender = $users->get($txn->sender_id);
            $recipient = $users->get($txn->recipient_id);
            if (!$sender || !$recipient) {
                throw new InvalidArgumentException('One of the parties to this entry no longer exists.');
            }

            $before = [
                'amount' => (string) $txn->amount,
                'payment_method' => $txn->payment_method,
                'bank_account_id' => $txn->bank_account_id,
                'category_id' => $txn->category_id,
                'remarks' => $txn->remarks,
                'created_at' => (string) $txn->created_at,
            ];

            $newAmount = array_key_exists('amount', $data) ? (string) $data['amount'] : (string) $txn->amount;
            $newMethod = $data['payment_method'] ?? $txn->payment_method;

            if ($txn->type === 'AUTO_ENTRY' && $newMethod !== 'CASH_ON_HAND') {
                throw new InvalidArgumentException('Cash Auto Entry entries are always cash on hand.');
            }
            $newBankId = $newMethod === 'BANK'
                ? ($data['bank_account_id'] ?? $txn->bank_account_id)
                : null;

            if ($newMethod === 'BANK' && empty($newBankId)) {
                throw new InvalidArgumentException('Choose a bank account for a bank payment.');
            }

            $old = $this->effects($txn->type, (string) $txn->amount, $txn->payment_method, $txn->bank_account_id);
            $new = $this->effects($txn->type, $newAmount, $newMethod, $newBankId);

            $delta = [];
            foreach (['sender', 'recipient'] as $side) {
                foreach (['cash', 'rtgs'] as $dim) {
                    $delta[$side][$dim] = bcsub($new[$side][$dim], $old[$side][$dim], 4);
                }
            }

            // 1. Balances.
            foreach (['sender' => $sender, 'recipient' => $recipient] as $side => $user) {
                $user->rak_cash_balance = bcadd((string) $user->rak_cash_balance, $delta[$side]['cash'], 4);
                $user->rak_rtgs_balance = bcadd((string) $user->rak_rtgs_balance, $delta[$side]['rtgs'], 4);
                $user->save();
            }

            $bankDeltas = [];
            foreach ($old['bank'] as $bankId => $amount) {
                $bankDeltas[$bankId] = bcsub($bankDeltas[$bankId] ?? '0', $amount, 4);
            }
            foreach ($new['bank'] as $bankId => $amount) {
                $bankDeltas[$bankId] = bcadd($bankDeltas[$bankId] ?? '0', $amount, 4);
            }
            ksort($bankDeltas);
            foreach ($bankDeltas as $bankId => $bankDelta) {
                if (bccomp($bankDelta, '0', 4) === 0) {
                    continue;
                }
                $bank = BankDetail::where('bank_id', $bankId)->lockForUpdate()->firstOrFail();
                $bank->ledger_balance = bcadd((string) $bank->ledger_balance, $bankDelta, 4);
                $bank->save();
            }

            // 2. This entry.
            $txn->amount = $newAmount;
            $txn->payment_method = $newMethod;
            $txn->bank_account_id = $newBankId;
            if (array_key_exists('category_id', $data)) {
                $txn->category_id = $data['category_id'];
            }
            if (array_key_exists('remarks', $data)) {
                $txn->remarks = $data['remarks'];
            }
            if (!empty($data['created_at'])) {
                $txn->created_at = $data['created_at'];
            }
            foreach (['sender', 'recipient'] as $side) {
                foreach (['cash', 'rtgs'] as $dim) {
                    $column = "{$side}_closing_{$dim}";
                    if ($txn->{$column} !== null) {
                        $txn->{$column} = bcadd((string) $txn->{$column}, $delta[$side][$dim], 4);
                    }
                }
            }
            $txn->save();

            // 3. Every later entry of either party.
            foreach (['sender' => $sender->user_id, 'recipient' => $recipient->user_id] as $side => $userId) {
                foreach (['cash', 'rtgs'] as $dim) {
                    if (bccomp($delta[$side][$dim], '0', 4) !== 0) {
                        $this->shiftLaterSnapshots($userId, $dim, $delta[$side][$dim], $txn->txn_id);
                    }
                }
            }

            $this->forgetCaches($txn, array_keys($bankDeltas));

            SystemLogService::log(
                'CASH',
                'CASH_EDIT',
                $actorId,
                $txn->txn_id,
                'cash_txn_details',
                "Edited cash entry #{$txn->txn_id} ({$txn->type}): amount {$before['amount']} → {$newAmount}, "
                . "{$before['payment_method']} → {$newMethod}",
                $data,
                [
                    'before' => $before,
                    'after' => [
                        'amount' => $newAmount,
                        'payment_method' => $newMethod,
                        'bank_account_id' => $newBankId,
                        'category_id' => $txn->category_id,
                        'remarks' => $txn->remarks,
                        'created_at' => (string) $txn->created_at,
                    ],
                    'balance_changes' => $delta,
                    'bank_changes' => $bankDeltas,
                ]
            );

            return $txn->fresh(['images', 'bank', 'givenByUser', 'givenToUser']);
        });
    }

    /**
     * What an entry did to each balance: ['sender' => [cash, rtgs],
     * 'recipient' => [cash, rtgs], 'bank' => [bank_id => amount]].
     */
    private function effects(string $type, string $amount, ?string $method, $bankId): array
    {
        $amount = bcadd($amount, '0', 4);
        $zero = ['cash' => '0', 'rtgs' => '0'];
        $effects = ['sender' => $zero, 'recipient' => $zero, 'bank' => []];

        // Cash Auto Entry only ever took cash from the sender.
        if ($type === 'AUTO_ENTRY') {
            $effects['sender']['cash'] = bcsub('0', $amount, 4);

            return $effects;
        }

        $dim = $method === 'CASH_ON_HAND' ? 'cash' : 'rtgs';
        $effects['sender'][$dim] = bcsub('0', $amount, 4);
        $effects['recipient'][$dim] = $amount;

        if ($method === 'BANK' && !empty($bankId)) {
            $effects['bank'][(int) $bankId] = $type === 'INCOME' ? $amount : bcsub('0', $amount, 4);
        }

        return $effects;
    }

    private function shiftLaterSnapshots(int $userId, string $dim, string $delta, int $afterTxnId): void
    {
        foreach (['sender', 'recipient'] as $side) {
            $opening = "{$side}_opening_{$dim}";
            $closing = "{$side}_closing_{$dim}";

            DB::update(
                "UPDATE cash_txn_details SET {$opening} = {$opening} + ?, {$closing} = {$closing} + ? "
                . "WHERE {$side}_id = ? AND txn_id > ?",
                [$delta, $delta, $userId, $afterTxnId]
            );
        }
    }

    private function forgetCaches(CashTxnDetail $txn, array $bankIds): void
    {
        Cache::forget("user:{$txn->sender_id}:balances");
        Cache::forget("user:{$txn->recipient_id}:balances");
        foreach ($bankIds as $bankId) {
            Cache::forget("bank:{$bankId}:balance");
        }
        if (Cache::supportsTags()) {
            Cache::tags(["cash_history_{$txn->sender_id}_{$txn->recipient_id}"])->flush();
            Cache::tags(["cash_history_{$txn->recipient_id}_{$txn->sender_id}"])->flush();
        }
    }
}
