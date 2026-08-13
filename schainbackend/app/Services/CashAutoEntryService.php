<?php

namespace App\Services;

use App\Models\CashTxnDetail;
use App\Models\CashTxnImage;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CashAutoEntryService
{
    /**
     * Store multiple cash auto entry transactions.
     * 
     * @param array $data
     * @return array Array of created CashTxnDetail models
     */
    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            
            $type = $data['type'];
            
            // Determine given_to and given_by based on type
            if ($type === 'EXPENSE') {
                $givenTo = $data['cash_user_id'];
                $givenBy = $data['head_id'];
            } elseif ($type === 'INCOME') {
                $givenTo = $data['head_id'];
                $givenBy = $data['cash_user_id'];
            } else { // AUTO_ENTRY
                $givenTo = $data['cash_user_id'];
                $givenBy = $data['head_id'];
            }

            $addedBy = request()->header('X-User-ID', 1);
            $createdTransactions = [];

            foreach ($data['transactions'] as $txnData) {
                
                // Fetch sender freshly for each iteration to get updated balance
                $sender = UserDetail::where('user_id', $givenBy)->lockForUpdate()->firstOrFail();
                $recipient = UserDetail::where('user_id', $givenTo)->lockForUpdate()->firstOrFail();

                $amount = (float) $txnData['amount'];

                // Deduct from sender's cash balance
                $senderOpeningCash = (float) $sender->cash_balance;
                $senderOpeningRtgs = (float) $sender->rtgs_balance;
                
                $recipientOpeningCash = (float) $recipient->cash_balance;
                $recipientOpeningRtgs = (float) $recipient->rtgs_balance;

                $sender->cash_balance = bcsub((string) $sender->cash_balance, (string) $amount, 4);
                $sender->save();
                
                // Note: According to legacy code, we only deduct from sender, we don't add to recipient in AUTO_ENTRY?
                // Wait! Let's check legacy code again. Legacy code only does:
                // $givenBy = \app\models\UserDetails::findOne($cashTxn->given_by);
                // $givenBy->rak_cash_balance = $givenBy->rak_cash_balance - $cashTxn->amount;
                // $givenBy->save(false);
                // It does NOT add to givenTo! This is because an Expense is money leaving the system.
                // We will replicate this exact behavior.

                $remarks = $txnData['remarks'] ?? 'AUTO_ENTRY';
                if (trim($remarks) === '') {
                    $remarks = 'AUTO_ENTRY';
                }

                $txnDate = $txnData['date'] ?? $txnData['added_at'] ?? $data['date'] ?? now();

                $txn = CashTxnDetail::create([
                    'type' => 'AUTO_ENTRY', // Legacy hardcoded it to AUTO_ENTRY
                    'sender_id' => $givenBy,
                    'recipient_id' => $givenTo,
                    'category_id' => $txnData['category_id'] ?? null,
                    'amount' => $amount,
                    'sender_opening_cash' => $senderOpeningCash,
                    'sender_opening_rtgs' => $senderOpeningRtgs,
                    'recipient_opening_cash' => $recipientOpeningCash,
                    'recipient_opening_rtgs' => $recipientOpeningRtgs,
                    'sender_closing_cash' => (float) $sender->cash_balance,
                    'sender_closing_rtgs' => (float) $sender->rtgs_balance,
                    'recipient_closing_cash' => (float) $recipient->cash_balance,
                    'recipient_closing_rtgs' => (float) $recipient->rtgs_balance,
                    'payment_method' => 'CASH_ON_HAND', // Auto Entry is always cash
                    'bank_account_id' => null,
                    'remarks' => $remarks,
                    'added_by' => $addedBy,
                ]);

                // Update the transaction dates
                DB::table('cash_txn_details')
                    ->where('txn_id', $txn->txn_id)
                    ->update([
                        'created_at' => $txnDate,
                        'updated_at' => $txnDate,
                    ]);
                
                $txn->created_at = $txnDate;
                $txn->updated_at = $txnDate;

                // Save attachment file paths if present for this specific row
                if (!empty($txnData['images'])) {
                    foreach ($txnData['images'] as $image) {
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

                $createdTransactions[] = $txn->load(['images']);
            }

            return $createdTransactions;
        });
    }
}
