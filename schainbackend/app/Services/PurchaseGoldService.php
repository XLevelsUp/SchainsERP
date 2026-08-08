<?php

namespace App\Services;

use App\Models\PurchaseGold;
use App\Models\CashToGoldAmountSource;
use App\Models\CashTxnDetail;
use App\Models\BillingEntry;
use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\BankDetail;
use Illuminate\Support\Facades\DB;
use App\Models\CashTxnImage;

class PurchaseGoldService extends BaseStockService
{
    /**
     * Store a Purchase Gold transaction atomically.
     */
    public function store(array $data, int $addedBy): PurchaseGold
    {
        return DB::transaction(function () use ($data, $addedBy) {
            // Lock users
            $head = UserDetail::where('user_id', $data['head_id'])->lockForUpdate()->firstOrFail();
            $customer = UserDetail::where('user_id', $data['customer_id'])->lockForUpdate()->firstOrFail();
            
            $createdTxnIds = [];
            
            $retailer = null;
            if (!empty($data['retailer_id'])) {
                $retailer = UserDetail::where('user_id', $data['retailer_id'])->lockForUpdate()->first();
            }

            // Capture opening balances
            $customerObGrams = (float) $customer->grams_grand_total;
            $customerObPurity = (float) $customer->purity_grand_total;
            $headObGrams = (float) $head->grams_grand_total;
            $headObPurity = (float) $head->purity_grand_total;

            $totalCash = (float) $data['total_cash'];
            $amntTransferToHead = (bool) $data['amnt_transfer_to_head'];

            // 1. Create PurchaseGold record
            $record = PurchaseGold::create([
                'type' => $data['type'],
                'head_id' => $data['head_id'],
                'customer_id' => $data['customer_id'],
                'total_cash' => $totalCash,
                'per_gram_cash' => $data['per_gram_cash'],
                'total_grams' => $data['total_grams'],
                'touch' => $data['touch'],
                'purity' => $data['purity'],
                'item_id' => $data['item_id'],
                'amnt_transfer_to_head' => $amntTransferToHead,
                'taken_total_cash' => $data['taken_total_cash'] ?? $totalCash,
                'taken_total_grams' => $data['taken_total_grams'] ?? $data['total_grams'],
                'taken_purity' => $data['taken_purity'] ?? $data['purity'],
                'ob_grams' => $customerObGrams,
                'ob_purity' => $customerObPurity,
                'remarks' => $data['remarks'] ?? null,
                'retailer_id' => $data['retailer_id'] ?? null,
                'is_rate_avg' => $data['is_rate_avg'] ?? false,
                'added_by' => $addedBy,
                'added_at' => $data['added_at'] ?? now(),
            ]);

            // 2. Update Cash/Bank and create CashTxnDetails
            if ($amntTransferToHead) {
                $amountSources = $data['amount_sources'] ?? [];
                
                // Validate total amount matches
                $sourcesSum = array_sum(array_column($amountSources, 'amount'));
                if (bccomp((string) $sourcesSum, (string) $totalCash, 2) !== 0) {
                    throw new \InvalidArgumentException("Sum of amount sources ({$sourcesSum}) must equal total_cash ({$totalCash}).");
                }

                // Check balances
                foreach ($amountSources as $src) {
                    $amt = (float) $src['amount'];
                    if ($src['source'] === 'CASH_ON_HAND') {
                        if ((float) $head->cash_balance < $amt) {
                            throw new \InvalidArgumentException("Insufficient cash balance. Head has {$head->cash_balance}, needs {$amt}.");
                        }
                    } elseif ($src['source'] === 'BANK') {
                        $bank = BankDetail::where('bank_id', $src['bank_id'])->lockForUpdate()->firstOrFail();
                        if ((float) $bank->ledger_balance < $amt) {
                            throw new \InvalidArgumentException("Insufficient bank balance. Bank has {$bank->ledger_balance}, needs {$amt}.");
                        }
                    }
                }

                // Deduct and write transactions
                foreach ($amountSources as $src) {
                    $amt = (float) $src['amount'];
                    $source = $src['source'];
                    $bankId = $src['bank_id'] ?? null;

                    $headObCashBefore = (float) $head->cash_balance;
                    $headObRtgsBefore = (float) $head->rtgs_balance;
                    $custObCashBefore = (float) $customer->cash_balance;
                    $custObRtgsBefore = (float) $customer->rtgs_balance;

                    if ($source === 'CASH_ON_HAND') {
                        $head->cash_balance = bcsub((string) $head->cash_balance, (string) $amt, 4);
                        $head->save();
                    } elseif ($source === 'BANK') {
                        $bank = BankDetail::where('bank_id', $bankId)->lockForUpdate()->firstOrFail();
                        $bank->ledger_balance = bcsub((string) $bank->ledger_balance, (string) $amt, 4);
                        $bank->save();
                    }

                    // Create amount source row
                    $amtSource = CashToGoldAmountSource::create([
                        'cash_to_gold_id' => $record->cash_to_gold_id,
                        'souce_type' => $source,
                        'bank_id' => $bankId,
                        'amount' => $amt,
                        'added_at' => now(),
                    ]);

                    // Create cash txn detail
                    // Head pays out, Customer receives
                    $txnType = ($data['type'] === 'OUT_CASH_CONVERTER') ? 'OUT_CASH_CONVERTER' : 'PURCHASE_GOLD';
                    $txn = CashTxnDetail::create([
                        'type' => $txnType,
                        'sender_id' => $data['head_id'],
                        'recipient_id' => $data['customer_id'],
                        'amount' => $amt,
                        'payment_method' => $source,
                        'bank_account_id' => $source === 'BANK' ? $bankId : null,
                        'sender_opening_cash' => $headObCashBefore,
                        'sender_opening_rtgs' => $headObRtgsBefore,
                        'recipient_opening_cash' => $custObCashBefore,
                        'recipient_opening_rtgs' => $custObRtgsBefore,
                        'sender_closing_cash' => (float) $head->cash_balance,
                        'sender_closing_rtgs' => (float) $head->rtgs_balance,
                        'recipient_closing_cash' => (float) $customer->cash_balance,
                        'recipient_closing_rtgs' => (float) $customer->rtgs_balance,
                        'remarks' => $txnType . ' | ' . ($data['remarks'] ?? ''),
                        'cash_to_gold_id' => $record->cash_to_gold_id,
                        'added_by' => $addedBy,
                    ]);

                    $createdTxnIds[] = $txn->txn_id;

                    $amtSource->cash_txn_id = $txn->txn_id;
                    $amtSource->save();
                }
            } else {
                // Journal entry: Customer cash balance decreases (no money touches head)
                $custObCashBefore = (float) $customer->cash_balance;
                $custObRtgsBefore = (float) $customer->rtgs_balance;
                $headObCashBefore = (float) $head->cash_balance;
                $headObRtgsBefore = (float) $head->rtgs_balance;

                if ($retailer) {
                    $retailer->cash_balance = bcsub((string) $retailer->cash_balance, (string) $totalCash, 4);
                    $retailer->save();
                } else {
                    $customer->cash_balance = bcsub((string) $customer->cash_balance, (string) $totalCash, 4);
                    $customer->save();
                }

                $txn = CashTxnDetail::create([
                    'type' => 'PURCHASE_GOLD',
                    'sender_id' => $data['head_id'],
                    'recipient_id' => $data['customer_id'],
                    'amount' => $totalCash,
                    'payment_method' => 'CASH_ON_HAND',
                    'sender_opening_cash' => $headObCashBefore,
                    'sender_opening_rtgs' => $headObRtgsBefore,
                    'recipient_opening_cash' => $custObCashBefore,
                    'recipient_opening_rtgs' => $custObRtgsBefore,
                    'sender_closing_cash' => (float) $head->cash_balance,
                    'sender_closing_rtgs' => (float) $head->rtgs_balance,
                    'recipient_closing_cash' => (float) $customer->fresh()->cash_balance,
                    'recipient_closing_rtgs' => (float) $customer->fresh()->rtgs_balance,
                    'remarks' => 'PURCHASE_GOLD Journal | ' . ($data['remarks'] ?? ''),
                    'cash_to_gold_id' => $record->cash_to_gold_id,
                    'added_by' => $addedBy,
                ]);

                $createdTxnIds[] = $txn->txn_id;
            }

            // 3. Billing Entry snapshot
            $billingType = ($data['type'] === 'OUT_CASH_CONVERTER') ? 'OUT' : 'IN';
            $billing = BillingEntry::create([
                'type' => $billingType,
                'head_id' => $data['head_id'],
                'user_id' => $data['customer_id'],
                'ob_purity' => $customerObPurity,
                'ob_grams' => $customerObGrams,
                'from_ob_purity' => $headObPurity,
                'from_ob_grams' => $headObGrams,
                'remarks' => ($data['remarks'] ?? '') ?: 'PURCHASE_GOLD',
                'added_at' => now(),
            ]);

            // 4. Create Stock Details
            $stockType = ($data['type'] === 'OUT_CASH_CONVERTER') ? 'OUT' : 'IN';
            $entryType = ($data['type'] === 'OUT_CASH_CONVERTER') ? 'OUT_CASH_CONVERTER' : 'CashToGold';
            
            $stock = StockDetails::create([
                'item_id' => $data['item_id'],
                'given_by' => ($stockType === 'IN') ? $data['customer_id'] : $data['head_id'],
                'given_to' => ($stockType === 'IN') ? $data['head_id'] : $data['customer_id'],
                'stock_type' => $stockType,
                'entry_type' => $entryType,
                'grams' => $data['total_grams'],
                'touch' => $data['touch'],
                'purity' => $data['purity'],
                'balance' => $data['purity'],
                'bill_id' => $billing->bill_id,
                'remarks' => $entryType,
                'retailer_id' => $data['retailer_id'] ?? null,
                'added_by' => $addedBy,
                'added_at' => now(),
            ]);

            // 5. Update Gold Balances
            if ($data['type'] === 'OUT_CASH_CONVERTER') {
                // OUT_CASH_CONVERTER: Customer (given_to) gold increases
                $this->updateUserItemBalance(
                    $data['customer_id'],
                    $data['item_id'],
                    (string) $data['total_grams'],
                    (string) $data['purity']
                );
                
                $customer->grams_grand_total = $this->add($customer->grams_grand_total, $data['total_grams']);
                $customer->purity_grand_total = $this->add($customer->purity_grand_total, $data['purity']);
                $customer->save();

                if ($retailer) {
                    $retailer->grams_grand_total = $this->add($retailer->grams_grand_total, $data['total_grams']);
                    $retailer->purity_grand_total = $this->add($retailer->purity_grand_total, $data['purity']);
                    $retailer->save();
                }
            } else {
                // Standard Purchase Gold (HEAD)
                if ($amntTransferToHead) {
                    // Head (given_to) gold increases
                    $this->updateUserItemBalance(
                        $data['head_id'],
                        $data['item_id'],
                        (string) $data['total_grams'],
                        (string) $data['purity']
                    );
                    // Customer (given_by) gold decreases
                    $this->updateUserItemBalance(
                        $data['customer_id'],
                        $data['item_id'],
                        '-' . $data['total_grams'],
                        '-' . $data['purity']
                    );

                    $head->grams_grand_total = $this->add($head->grams_grand_total, $data['total_grams']);
                    $head->purity_grand_total = $this->add($head->purity_grand_total, $data['purity']);
                    $head->save();

                    $customer->grams_grand_total = $this->sub($customer->grams_grand_total, $data['total_grams']);
                    $customer->purity_grand_total = $this->sub($customer->purity_grand_total, $data['purity']);
                    $customer->save();

                    if ($retailer) {
                        $retailer->grams_grand_total = $this->sub($retailer->grams_grand_total, $data['total_grams']);
                        $retailer->purity_grand_total = $this->sub($retailer->purity_grand_total, $data['purity']);
                        $retailer->save();
                    }
                } else {
                    // Journal entry: Customer (given_by) gold increases
                    $this->updateUserItemBalance(
                        $data['customer_id'],
                        $data['item_id'],
                        (string) $data['total_grams'],
                        (string) $data['purity']
                    );

                    $customer->grams_grand_total = $this->add($customer->grams_grand_total, $data['total_grams']);
                    $customer->purity_grand_total = $this->add($customer->purity_grand_total, $data['purity']);
                    $customer->save();

                    if ($retailer) {
                        $retailer->grams_grand_total = $this->add($retailer->grams_grand_total, $data['total_grams']);
                        $retailer->purity_grand_total = $this->add($retailer->purity_grand_total, $data['purity']);
                        $retailer->save();
                    }
                }
            }

            // Refresh models after save
            $head->refresh();
            $customer->refresh();

            // 6. Update Billing closing balances
            $billing->cb_purity = (float) $customer->purity_grand_total;
            $billing->cb_grams = (float) $customer->grams_grand_total;
            $billing->from_cb_purity = (float) $head->purity_grand_total;
            $billing->from_cb_grams = (float) $head->grams_grand_total;
            $billing->save();

            // Back-fill stock_id
            $record->stock_id = $stock->stock_id;
            $record->save();

            // Save uploaded receipt images if present
            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    if ($image->isValid()) {
                        $path = $image->store('cash_txn_images', 'public');
                        foreach ($createdTxnIds as $txnId) {
                            CashTxnImage::create([
                                'cash_txn_id' => $txnId,
                                'image_path'  => $path,
                            ]);
                        }
                    }
                }
            }

            return $record->load(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item']);
        });
    }

    /**
     * Find a single record.
     */
    public function find(int $id): PurchaseGold
    {
        return PurchaseGold::with(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item', 'stock'])->findOrFail($id);
    }
}
