<?php

namespace App\Services;

use App\Models\BankDetail;
use App\Models\UserDetail;
use App\Models\CashTxnDetail;
use App\Models\CashTxnImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CashTxnDetailService
{
    /**
     * Get user cash and RTGS balances with caching.
     */
    public function getUserBalances(int $userId): array
    {
        return Cache::remember("user:{$userId}:balances", 86400, function () use ($userId) {
            $user = UserDetail::findOrFail($userId);
            return [
                'cash_balance' => (float) $user->cash_balance,
                'rtgs_balance' => (float) $user->rtgs_balance,
            ];
        });
    }

    /**
     * Get bank account balance with caching.
     */
    public function getBankBalance(int $bankId): float
    {
        return (float) Cache::remember("bank:{$bankId}:balance", 86400, function () use ($bankId) {
            $bank = BankDetail::findOrFail($bankId);
            return (float) $bank->ledger_balance;
        });
    }

    /**
     * Store cash inflow (Income).
     */
    public function storeIncome(array $data, int $addedBy): CashTxnDetail
    {
        return DB::transaction(function () use ($data, $addedBy) {
            // Pessimistic locks for balance updates
            $sender = UserDetail::where('user_id', $data['sender_id'])->lockForUpdate()->firstOrFail();
            $recipient = UserDetail::where('user_id', $data['recipient_id'])->lockForUpdate()->firstOrFail();
            
            $paymentMethod = $data['payment_method'];
            $amount = (float) $data['amount'];
            $bankId = $data['bank_account_id'] ?? null;

            $senderOpeningCash = (float) $sender->cash_balance;
            $senderOpeningRtgs = (float) $sender->rtgs_balance;
            $recipientOpeningCash = (float) $recipient->cash_balance;
            $recipientOpeningRtgs = (float) $recipient->rtgs_balance;

            $bank = null;
            if ($paymentMethod === 'BANK' && $bankId) {
                $bank = BankDetail::where('bank_id', $bankId)->lockForUpdate()->firstOrFail();
            }

            // Calculations & double-entry updates
            if ($paymentMethod === 'CASH_ON_HAND') {
                $sender->rak_cash_balance = bcsub((string)$sender->cash_balance, (string)$amount, 4);
                $recipient->rak_cash_balance = bcadd((string)$recipient->cash_balance, (string)$amount, 4);
            } else {
                $sender->rak_rtgs_balance = bcsub((string)$sender->rtgs_balance, (string)$amount, 4);
                $recipient->rak_rtgs_balance = bcadd((string)$recipient->rtgs_balance, (string)$amount, 4);
                
                if ($bank) {
                    $bank->ledger_balance = bcadd((string)$bank->ledger_balance, (string)$amount, 4);
                    $bank->save();
                }
            }

            $sender->save();
            $recipient->save();

            // Create main transaction
            $txn = CashTxnDetail::create([
                'type' => 'INCOME',
                'sender_id' => $sender->user_id,
                'recipient_id' => $recipient->user_id,
                'category_id' => $data['category_id'] ?? null,
                'amount' => $amount,
                'sender_opening_cash' => $senderOpeningCash,
                'sender_opening_rtgs' => $senderOpeningRtgs,
                'recipient_opening_cash' => $recipientOpeningCash,
                'recipient_opening_rtgs' => $recipientOpeningRtgs,
                'sender_closing_cash' => (float) $sender->cash_balance,
                'sender_closing_rtgs' => (float) $sender->rtgs_balance,
                'recipient_closing_cash' => (float) $recipient->cash_balance,
                'recipient_closing_rtgs' => (float) $recipient->rtgs_balance,
                'payment_method' => $paymentMethod,
                'bank_account_id' => $bankId,
                'remarks' => $data['remarks'] ?? null,
                'added_by' => $addedBy,
            ]);

            // Save attachment file paths if present
            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    if ($image->isValid()) {
                        $path = $image->store('cash_txn_images', 'public');
                        CashTxnImage::create([
                            'cash_txn_id' => $txn->txn_id,
                            'image_path' => $path,
                        ]);
                    }
                }
            }

            // Invalidate caches
            Cache::forget("user:{$sender->user_id}:balances");
            Cache::forget("user:{$recipient->user_id}:balances");
            if ($bankId) {
                Cache::forget("bank:{$bankId}:balance");
            }
            if (Cache::supportsTags()) {
                Cache::tags(["cash_history_{$sender->user_id}_{$recipient->user_id}"])->flush();
                Cache::tags(["cash_history_{$recipient->user_id}_{$sender->user_id}"])->flush();
            }

            return $txn;
        });
    }

    /**
     * Store cash outflow (Expense).
     */
    public function storeExpense(array $data, int $addedBy): CashTxnDetail
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $sender = UserDetail::where('user_id', $data['sender_id'])->lockForUpdate()->firstOrFail();
            $recipient = UserDetail::where('user_id', $data['recipient_id'])->lockForUpdate()->firstOrFail();
            
            $paymentMethod = $data['payment_method'];
            $amount = (float) $data['amount'];
            $bankId = $data['bank_account_id'] ?? null;

            $senderOpeningCash = (float) $sender->cash_balance;
            $senderOpeningRtgs = (float) $sender->rtgs_balance;
            $recipientOpeningCash = (float) $recipient->cash_balance;
            $recipientOpeningRtgs = (float) $recipient->rtgs_balance;

            $bank = null;
            if ($paymentMethod === 'BANK' && $bankId) {
                $bank = BankDetail::where('bank_id', $bankId)->lockForUpdate()->firstOrFail();
            }

            if ($paymentMethod === 'CASH_ON_HAND') {
                $sender->rak_cash_balance = bcsub((string)$sender->cash_balance, (string)$amount, 4);
                $recipient->rak_cash_balance = bcadd((string)$recipient->cash_balance, (string)$amount, 4);
            } else {
                $sender->rak_rtgs_balance = bcsub((string)$sender->rtgs_balance, (string)$amount, 4);
                $recipient->rak_rtgs_balance = bcadd((string)$recipient->rtgs_balance, (string)$amount, 4);
                
                if ($bank) {
                    $bank->ledger_balance = bcsub((string)$bank->ledger_balance, (string)$amount, 4);
                    $bank->save();
                }
            }

            $sender->save();
            $recipient->save();

            $txn = CashTxnDetail::create([
                'type' => 'EXPENSE',
                'sender_id' => $sender->user_id,
                'recipient_id' => $recipient->user_id,
                'category_id' => $data['category_id'] ?? null,
                'amount' => $amount,
                'sender_opening_cash' => $senderOpeningCash,
                'sender_opening_rtgs' => $senderOpeningRtgs,
                'recipient_opening_cash' => $recipientOpeningCash,
                'recipient_opening_rtgs' => $recipientOpeningRtgs,
                'sender_closing_cash' => (float) $sender->cash_balance,
                'sender_closing_rtgs' => (float) $sender->rtgs_balance,
                'recipient_closing_cash' => (float) $recipient->cash_balance,
                'recipient_closing_rtgs' => (float) $recipient->rtgs_balance,
                'payment_method' => $paymentMethod,
                'bank_account_id' => $bankId,
                'remarks' => $data['remarks'] ?? null,
                'added_by' => $addedBy,
            ]);

            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    if ($image->isValid()) {
                        $path = $image->store('cash_txn_images', 'public');
                        CashTxnImage::create([
                            'cash_txn_id' => $txn->txn_id,
                            'image_path' => $path,
                        ]);
                    }
                }
            }

            Cache::forget("user:{$sender->user_id}:balances");
            Cache::forget("user:{$recipient->user_id}:balances");
            if ($bankId) {
                Cache::forget("bank:{$bankId}:balance");
            }
            if (Cache::supportsTags()) {
                Cache::tags(["cash_history_{$sender->user_id}_{$recipient->user_id}"])->flush();
                Cache::tags(["cash_history_{$recipient->user_id}_{$sender->user_id}"])->flush();
            }

            return $txn;
        });
    }
}
