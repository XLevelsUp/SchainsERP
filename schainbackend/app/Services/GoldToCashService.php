<?php

namespace App\Services;

use App\Models\BankDetail;
use App\Models\BillingEntry;
use App\Models\CashToGold;
use App\Models\CashToGoldAmountSource;
use App\Models\CashTxnDetail;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;

class GoldToCashService extends BaseStockService
{
    /**
     * Store a full Gold To Cash transaction atomically.
     *
     * Tables written (in order inside one DB::transaction):
     *  1. cash_to_gold                  — type='GOLD_TO_CASH' record
     *  2. cash_to_gold_amount_sources   — N rows (one per source: CASH_ON_HAND or BANK)
     *  3. user_details / bank_details   — head balance DECREMENTED per source
     *  4. cash_txn_details              — audit ledger (given_by=head, given_to=customer)
     *  5. billing_entries               — OB/CB gold snapshot
     *  6. stock_details                 — gold stock IN + customer grams updated
     *  7. cash_to_gold (update)         — back-fill stock_id
     */
    public function store(array $data, int $addedBy): CashToGold
    {
        return DB::transaction(function () use ($data, $addedBy) {

            // ── Lock users to prevent concurrent balance race conditions ──────
            $head = UserDetail::where('user_id', $data['head_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $customer = UserDetail::where('user_id', $data['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $amountSources = $data['amount_sources'];

            // ── Validate sources total == total_cash ──────────────────────────
            $sourcesTotal = array_sum(array_column($amountSources, 'amount'));
            if (bccomp((string) $sourcesTotal, (string) $data['total_cash'], 2) !== 0) {
                throw new \InvalidArgumentException(
                    "Sum of amount sources ({$sourcesTotal}) must equal total_cash ({$data['total_cash']})."
                );
            }

            // ── Validate head has sufficient balance ──────────────────────────
            foreach ($amountSources as $src) {
                if ($src['source'] === 'CASH_ON_HAND') {
                    $available = (float) $head->cash_balance;
                    if ($available < (float) $src['amount']) {
                        throw new \InvalidArgumentException(
                            "Insufficient cash. Head has {$available}, needs {$src['amount']}."
                        );
                    }
                } elseif ($src['source'] === 'BANK') {
                    $bank      = BankDetail::findOrFail($src['bank_id']);
                    $available = (float) $bank->ledger_balance;
                    if ($available < (float) $src['amount']) {
                        throw new \InvalidArgumentException(
                            "Insufficient bank balance. Bank has {$available}, needs {$src['amount']}."
                        );
                    }
                }
            }

            // ── Capture opening balances (before any write) ───────────────────
            // In Gold To Cash: frontend sends total_gold → stored in `purity`
            //                              total_grams → stored in `total_grams`
            $customerObGrams  = (float) $customer->grams_grand_total;
            $customerObPurity = (float) $customer->purity_grand_total;
            $headObGrams      = (float) $head->grams_grand_total;
            $headObPurity     = (float) $head->purity_grand_total;

            // ── 1. Save primary cash_to_gold record ───────────────────────────
            // Field mapping: total_gold (frontend) → purity (db column)
            //                total_grams (frontend) → total_grams (db column)
            $record = CashToGold::create([
                'type'                  => 'GOLD_TO_CASH',
                'head_id'               => $data['head_id'],
                'customer_id'           => $data['customer_id'],
                'total_cash'            => $data['total_cash'],
                'per_gram_cash'         => $data['per_gram_cash'],
                'total_grams'           => $data['total_grams'],       // touch-adjusted weight
                'touch'                 => $data['touch'],
                'purity'                => $data['total_gold'],        // total pure gold (swap!)
                'item_id'               => 3,                          // hardcoded as per legacy
                'amnt_transfer_to_head' => true,                       // always true for G2C
                'ob_grams'              => $customerObGrams,
                'ob_purity'             => $customerObPurity,
                'remarks'               => $data['remarks'] ?? null,
                'retailer_id'           => $data['retailer_id'] ?? null,
                'is_rate_avg'           => $data['is_rate_avg'] ?? false,
                'added_by'              => $addedBy,
                'added_at'              => $data['added_at'] ?? now(),
            ]);

            // ── 2, 3, 4. Process each amount source ───────────────────────────
            foreach ($amountSources as $src) {
                $source  = $src['source'];   // CASH_ON_HAND | BANK
                $bankId  = $src['bank_id'] ?? null;
                $amount  = (float) $src['amount'];

                // Capture opening balances before this source's deduction
                $headObCashBefore  = (float) $head->cash_balance;
                $headObRtgsBefore  = (float) $head->rtgs_balance;
                $custObCashBefore  = (float) $customer->cash_balance;
                $custObRtgsBefore  = (float) $customer->rtgs_balance;
                $bankObBalance     = 0.0;

                // ── 3. Head pays out — decrement balance ───────────────────
                if ($source === 'CASH_ON_HAND') {
                    $head->cash_balance = bcsub(
                        (string) $head->cash_balance,
                        (string) $amount,
                        4
                    );
                    $head->save();
                } elseif ($source === 'BANK') {
                    $bank = BankDetail::where('bank_id', $bankId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $bankObBalance = (float) $bank->ledger_balance;

                    $bank->ledger_balance = bcsub(
                        (string) $bank->ledger_balance,
                        (string) $amount,
                        4
                    );
                    $bank->save();
                }

                // ── 2. Save amount source row ──────────────────────────────
                $amtSource = CashToGoldAmountSource::create([
                    'cash_to_gold_id' => $record->cash_to_gold_id,
                    'souce_type'      => $source,
                    'bank_id'         => $bankId,
                    'amount'          => $amount,
                    'added_at'        => now(),
                ]);

                // ── 4. Create cash_txn_details ────────────────────────────
                // sender = head (pays cash), recipient = customer (receives cash)
                $txn = CashTxnDetail::create([
                    'type'                   => 'GOLD_TO_CASH',
                    'sender_id'              => $data['head_id'],
                    'recipient_id'           => $data['customer_id'],
                    'amount'                 => $amount,
                    'payment_method'         => $source,
                    'bank_account_id'        => $source === 'BANK' ? $bankId : null,
                    'sender_opening_cash'    => $headObCashBefore,
                    'sender_opening_rtgs'    => $headObRtgsBefore,
                    'recipient_opening_cash' => $custObCashBefore,
                    'recipient_opening_rtgs' => $custObRtgsBefore,
                    'sender_closing_cash'    => (float) $head->cash_balance,
                    'sender_closing_rtgs'    => (float) $head->rtgs_balance,
                    'recipient_closing_cash' => (float) $customer->cash_balance,
                    'recipient_closing_rtgs' => (float) $customer->rtgs_balance,
                    'remarks'                => 'GOLD_TO_CASH | '
                                               . $data['total_gold'] . ' gold * '
                                               . $data['per_gram_cash'] . ' = '
                                               . $data['total_cash'],
                    'cash_to_gold_id'        => $record->cash_to_gold_id,
                    'added_by'               => $addedBy,
                ]);

                // Back-fill cash_txn_id into the amount source
                $amtSource->cash_txn_id = $txn->txn_id;
                $amtSource->save();
            }

            // ── 5. Create billing_entries (OB snapshot) ───────────────────────
            $billing = BillingEntry::create([
                'type'           => 'IN',
                'head_id'        => $data['head_id'],
                'user_id'        => $data['customer_id'],
                'ob_purity'      => $customerObPurity,
                'ob_grams'       => $customerObGrams,
                'from_ob_purity' => $headObPurity,
                'from_ob_grams'  => $headObGrams,
                'remarks'        => $data['total_gold'] . ' * ' . $data['per_gram_cash'] . ' = ' . $data['total_cash'],
                'added_at'       => now(),
            ]);

            // ── 6. Create stock_details (gold stock IN — gold deposited back) ─
            // given_by=head, given_to=customer (gold moves to customer's account)
            $stock = StockDetails::create([
                'item_id'    => 3,                           // same hardcoded item as legacy
                'given_by'   => $data['head_id'],
                'given_to'   => $data['customer_id'],
                'stock_type' => 'IN',
                'entry_type' => 'GOLDCASHCONVERSION',
                'grams'      => $data['total_grams'],        // touch-adjusted grams
                'touch'      => $data['touch'],
                'purity'     => $data['total_gold'],         // total pure gold
                'balance'    => $data['total_gold'],
                'bill_id'    => $billing->bill_id,
                'remarks'    => 'GOLD_TO_CASH',
                'added_by'   => $addedBy,
                'added_at'   => now(),
            ]);

            // ── Update customer's gold balances (grams & purity increase) ─────
            $this->updateUserItemBalance(
                $data['customer_id'],
                3,  // item_id hardcoded as per legacy
                (string) $data['total_grams'],
                (string) $data['total_gold']
            );

            // ── Update head's gold balances (loses gold) ─────────────────
            $this->updateUserItemBalance(
                $data['head_id'],
                3,
                '-' . $data['total_grams'],
                '-' . $data['total_gold']
            );

            $customer->grams_grand_total = $this->add($customer->grams_grand_total, $data['total_grams']);
            $customer->purity_grand_total = $this->add($customer->purity_grand_total, $data['total_gold']);
            $customer->save();

            $head->grams_grand_total = $this->sub($head->grams_grand_total, $data['total_grams']);
            $head->purity_grand_total = $this->sub($head->purity_grand_total, $data['total_gold']);
            $head->save();

            // Refresh customer and head after gold balance update
            $customer->refresh();
            $head->refresh();


            // ── Update billing_entries CB (closing balance) ───────────────────
            $billing->cb_purity      = (float) $customer->purity_grand_total;
            $billing->cb_grams       = (float) $customer->grams_grand_total;
            $billing->from_cb_purity = (float) $head->purity_grand_total;
            $billing->from_cb_grams  = (float) $head->grams_grand_total;
            $billing->save();

            // ── 7. Back-fill stock_id into cash_to_gold ───────────────────────
            $record->stock_id = $stock->stock_id;
            $record->save();

            return $record->load(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item']);
        });
    }

    /**
     * List Gold To Cash records with filters.
     */
    public function index(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = CashToGold::where('type', 'GOLD_TO_CASH')
            ->with(['amountSources', 'head', 'customer', 'item']);

        if (!empty($filters['head_id'])) {
            $query->where('head_id', $filters['head_id']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('added_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('added_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('cash_to_gold_id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find a single Gold To Cash record.
     */
    public function find(int $id): CashToGold
    {
        return CashToGold::where('type', 'GOLD_TO_CASH')
            ->with(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item', 'stock'])
            ->findOrFail($id);
    }

    /**
     * Delete Gold To Cash record and reverse all balance changes.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {

            $record = CashToGold::where('type', 'GOLD_TO_CASH')
                ->with(['amountSources'])
                ->lockForUpdate()
                ->findOrFail($id);

            $head     = UserDetail::where('user_id', $record->head_id)->lockForUpdate()->firstOrFail();
            $customer = UserDetail::where('user_id', $record->customer_id)->lockForUpdate()->firstOrFail();

            // Reverse each amount source — add back to head
            foreach ($record->amountSources as $src) {
                if ($src->souce_type === 'CASH_ON_HAND') {
                    $head->cash_balance = bcadd(
                        (string) $head->cash_balance,
                        (string) $src->amount,
                        4
                    );
                    $head->save();
                } elseif ($src->souce_type === 'BANK' && $src->bank_id) {
                    $bank = BankDetail::where('bank_id', $src->bank_id)->lockForUpdate()->first();
                    if ($bank) {
                        $bank->ledger_balance = bcadd(
                            (string) $bank->ledger_balance,
                            (string) $src->amount,
                            4
                        );
                        $bank->save();
                    }
                }
            }

            // Reverse gold balance on customer
            $this->updateUserItemBalance(
                $record->customer_id,
                3,
                '-' . $record->total_grams,
                '-' . $record->purity
            );

            // Soft-delete linked records
            $record->cashTxnDetails()->delete();
            $record->amountSources()->delete();

            if ($record->stock_id) {
                StockDetails::where('stock_id', $record->stock_id)->delete();
            }

            $record->delete();

            return true;
        });
    }
}
