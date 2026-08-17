<?php

namespace App\Services;

use App\Models\SaleGold;
use App\Models\CashToGoldAmountSource;
use App\Models\CashTxnDetail;
use App\Models\BillingEntry;
use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\BankDetail;
use Illuminate\Support\Facades\DB;
use App\Models\CashTxnImage;

class SaleGoldService extends BaseStockService
{
    /**
     * Store a Sale Gold transaction atomically.
     */
    public function store(array $data, int $addedBy): SaleGold
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

            // 1. Create SaleGold record
            $record = SaleGold::create([
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
                'is_live' => $data['is_live'] ?? false,
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

                // Deduct and write transactions
                foreach ($amountSources as $src) {
                    $amt = (float) $src['amount'];
                    $source = $src['source'];
                    $bankId = $src['bank_id'] ?? null;

                    $headObCashBefore = (float) $head->cash_balance;
                    $headObRtgsBefore = (float) $head->rtgs_balance;
                    $custObCashBefore = (float) $customer->cash_balance;
                    $custObRtgsBefore = (float) $customer->rtgs_balance;

                    // Customer pays cash to Head:
                    // Head cash/bank balance INCREASES
                    // Customer cash balance DECREASES
                    if ($source === 'CASH_ON_HAND') {
                        $head->cash_balance = bcadd((string) $head->cash_balance, (string) $amt, 4);
                        $head->save();

                        if ($retailer) {
                            $retailer->cash_balance = bcsub((string) $retailer->cash_balance, (string) $amt, 4);
                            $retailer->save();
                        } else {
                            $customer->cash_balance = bcsub((string) $customer->cash_balance, (string) $amt, 4);
                            $customer->save();
                        }
                    } elseif ($source === 'BANK') {
                        $bank = BankDetail::where('bank_id', $bankId)->lockForUpdate()->firstOrFail();
                        $bank->ledger_balance = bcadd((string) $bank->ledger_balance, (string) $amt, 4);
                        $bank->save();

                        if ($retailer) {
                            $retailer->rtgs_balance = bcsub((string) $retailer->rtgs_balance, (string) $amt, 4);
                            $retailer->save();
                        } else {
                            $customer->rtgs_balance = bcsub((string) $customer->rtgs_balance, (string) $amt, 4);
                            $customer->save();
                        }
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
                    // Customer pays out, Head receives
                    $txnType = ($data['type'] === 'IN_CASH_CONVERTER') ? 'IN_CASH_CONVERTER' : 'SALE_GOLD';
                    $txn = CashTxnDetail::create([
                        'type' => $txnType,
                        'sender_id' => $data['customer_id'],
                        'recipient_id' => $data['head_id'],
                        'amount' => $amt,
                        'payment_method' => $source,
                        'bank_account_id' => $source === 'BANK' ? $bankId : null,
                        'sender_opening_cash' => $custObCashBefore,
                        'sender_opening_rtgs' => $custObRtgsBefore,
                        'recipient_opening_cash' => $headObCashBefore,
                        'recipient_opening_rtgs' => $headObRtgsBefore,
                        'sender_closing_cash' => (float) $customer->cash_balance,
                        'sender_closing_rtgs' => (float) $customer->rtgs_balance,
                        'recipient_closing_cash' => (float) $head->cash_balance,
                        'recipient_closing_rtgs' => (float) $head->rtgs_balance,
                        'remarks' => $txnType . ' | ' . ($data['remarks'] ?? ''),
                        'bank_entry_date' => $data['bank_entry_date'] ?? null,
                        'cash_to_gold_id' => $record->cash_to_gold_id,
                        'added_by' => $addedBy,
                    ]);

                    $createdTxnIds[] = $txn->txn_id;

                    $amtSource->cash_txn_id = $txn->txn_id;
                    $amtSource->save();
                }
            } else {
                // Journal entry: Retailer/Customer cash balance increases
                $custObCashBefore = (float) $customer->cash_balance;
                $custObRtgsBefore = (float) $customer->rtgs_balance;
                $headObCashBefore = (float) $head->cash_balance;
                $headObRtgsBefore = (float) $head->rtgs_balance;

                if ($retailer) {
                    $retailer->cash_balance = bcadd((string) $retailer->cash_balance, (string) $totalCash, 4);
                    $retailer->save();
                } else {
                    $customer->cash_balance = bcadd((string) $customer->cash_balance, (string) $totalCash, 4);
                    $customer->save();
                }

                $txn = CashTxnDetail::create([
                    'type' => 'SALE_GOLD',
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
                    'recipient_closing_cash' => (float) $customer->cash_balance,
                    'recipient_closing_rtgs' => (float) $customer->rtgs_balance,
                    'remarks' => 'SALE_GOLD Journal | ' . ($data['remarks'] ?? ''),
                    'bank_entry_date' => $data['bank_entry_date'] ?? null,
                    'cash_to_gold_id' => $record->cash_to_gold_id,
                    'added_by' => $addedBy,
                ]);

                $createdTxnIds[] = $txn->txn_id;
            }

            // 3. Billing Entry snapshot
            $billingType = ($data['type'] === 'IN_CASH_CONVERTER') ? 'IN' : 'OUT';
            $billing = BillingEntry::create([
                'type' => $billingType,
                'head_id' => $data['head_id'],
                'user_id' => $data['customer_id'],
                'ob_purity' => $customerObPurity,
                'ob_grams' => $customerObGrams,
                'from_ob_purity' => $headObPurity,
                'from_ob_grams' => $headObGrams,
                'remarks' => ($data['remarks'] ?? '') ?: 'SALE_GOLD',
                'added_at' => now(),
            ]);

            // 4. Create Stock Details
            $stockType = ($data['type'] === 'IN_CASH_CONVERTER') ? 'IN' : 'OUT';
            $entryType = 'NORMAL';
            if ($data['type'] === 'SALE_GOLD_CASH') {
                $entryType = 'GOLDCASHCONVERSION';
            } elseif ($data['type'] === 'IN_CASH_CONVERTER') {
                $entryType = 'IN_CASH_CONVERTER';
            } elseif ($data['type'] === 'SALE_GOLD') {
                $entryType = 'SALES';
            }

            // Lock and update stock_in_id balance if referenced
            if (!empty($data['stock_in_id'])) {
                $stockIn = StockDetails::where('stock_id', $data['stock_in_id'])->lockForUpdate()->firstOrFail();
                $stockIn->balance = bcsub((string) $stockIn->balance, (string) $data['purity'], 4);
                $stockIn->save();
            }

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
                'remarks' => ($data['type'] === 'IN_CASH_CONVERTER') ? 'IN_CASH_CONVERTER' : 'SALE_GOLD',
                'stock_in_id' => $data['stock_in_id'] ?? null,
                'retailer_id' => $data['retailer_id'] ?? null,
                'added_by' => $addedBy,
                'added_at' => now(),
            ]);

            // 5. Update Gold Balances
            if ($data['type'] === 'IN_CASH_CONVERTER') {
                // IN_CASH_CONVERTER: Customer gold balance decreases
                $this->updateUserItemBalance(
                    $data['customer_id'],
                    $data['item_id'],
                    '-' . $data['total_grams'],
                    '-' . $data['purity']
                );
                
                $customer->grams_grand_total = $this->sub($customer->grams_grand_total, $data['total_grams']);
                $customer->purity_grand_total = $this->sub($customer->purity_grand_total, $data['purity']);
                $customer->save();

                if ($retailer) {
                    $retailer->grams_grand_total = $this->sub($retailer->grams_grand_total, $data['total_grams']);
                    $retailer->purity_grand_total = $this->sub($retailer->purity_grand_total, $data['purity']);
                    $retailer->save();
                }
            } else {
                // Standard Sale Gold / Sale Gold Cash
                if ($amntTransferToHead) {
                    // Head (given_by) gold decreases
                    $this->updateUserItemBalance(
                        $data['head_id'],
                        $data['item_id'],
                        '-' . $data['total_grams'],
                        '-' . $data['purity']
                    );
                    // Customer (given_to) gold increases
                    $this->updateUserItemBalance(
                        $data['customer_id'],
                        $data['item_id'],
                        (string) $data['total_grams'],
                        (string) $data['purity']
                    );

                    $head->grams_grand_total = $this->sub($head->grams_grand_total, $data['total_grams']);
                    $head->purity_grand_total = $this->sub($head->purity_grand_total, $data['purity']);
                    $head->save();

                    $customer->grams_grand_total = $this->add($customer->grams_grand_total, $data['total_grams']);
                    $customer->purity_grand_total = $this->add($customer->purity_grand_total, $data['purity']);
                    $customer->save();

                    if ($retailer) {
                        $retailer->grams_grand_total = $this->add($retailer->grams_grand_total, $data['total_grams']);
                        $retailer->purity_grand_total = $this->add($retailer->purity_grand_total, $data['purity']);
                        $retailer->save();
                    }
                } else {
                    // Journal entry: Customer (given_to) gold decreases
                    $this->updateUserItemBalance(
                        $data['customer_id'],
                        $data['item_id'],
                        '-' . $data['total_grams'],
                        '-' . $data['purity']
                    );

                    $customer->grams_grand_total = $this->sub($customer->grams_grand_total, $data['total_grams']);
                    $customer->purity_grand_total = $this->sub($customer->purity_grand_total, $data['purity']);
                    $customer->save();

                    if ($retailer) {
                        $retailer->grams_grand_total = $this->sub($retailer->grams_grand_total, $data['total_grams']);
                        $retailer->purity_grand_total = $this->sub($retailer->purity_grand_total, $data['purity']);
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
    public function find(int $id): SaleGold
    {
        return SaleGold::with(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item', 'stock'])->findOrFail($id);
    }
}
