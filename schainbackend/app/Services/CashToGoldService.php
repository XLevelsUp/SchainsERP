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

class CashToGoldService extends BaseStockService
{
    /**
     * Store a full Cash To Gold transaction atomically.
     *
     * Tables written (in order inside one DB::transaction):
     *  1. cash_to_gold                  — primary record
     *  2. cash_to_gold_amount_sources   — N rows (one per source)
     *  3. user_details / bank_details   — balance updates (one per source)
     *  4. cash_txn_details              — audit ledger (one per source)
     *  5. billing_entries               — OB/CB gold snapshot
     *  6. stock_details                 — stock IN movement + user grams update
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

            $amountSources = $data['amount_sources'] ?? [];
            $transferToHead = (bool) ($data['amnt_transfer_to_head'] ?? true);

            // ── Validate sources total == total_cash (when transfer=true) ────
            if ($transferToHead && count($amountSources) > 0) {
                $sourcesTotal = array_sum(array_column($amountSources, 'amount'));
                if (bccomp((string)$sourcesTotal, (string)$data['total_cash'], 2) !== 0) {
                    throw new \InvalidArgumentException(
                        "Sum of amount sources ({$sourcesTotal}) must equal total_cash ({$data['total_cash']})."
                    );
                }
            }

            // ── Capture opening balances (before any write) ──────────────────
            $customerObGrams  = (float) $customer->grams_grand_total;
            $customerObPurity = (float) $customer->purity_grand_total;
            $headObGrams      = (float) $head->grams_grand_total;
            $headObPurity     = (float) $head->purity_grand_total;

            // ── 1. Save primary cash_to_gold record ──────────────────────────
            $record = CashToGold::create([
                'type'                  => 'CUSTOMER',
                'head_id'               => $data['head_id'],
                'customer_id'           => $data['customer_id'],
                'total_cash'            => $data['total_cash'],
                'per_gram_cash'         => $data['per_gram_cash'],
                'total_grams'           => $data['total_grams'],
                'touch'                 => $data['touch'],
                'purity'                => $data['purity'],
                'item_id'               => $data['item_id'],
                'amnt_transfer_to_head' => $transferToHead,
                'ob_grams'              => $customerObGrams,
                'ob_purity'             => $customerObPurity,
                'remarks'               => $data['remarks'] ?? null,
                'retailer_id'           => $data['retailer_id'] ?? null,
                'is_rate_avg'           => $data['is_rate_avg'] ?? false,
                'added_by'              => $addedBy,
                'added_at'              => $data['added_at'] ?? now(),
            ]);

            // ── 2 & 3 & 4. Process each amount source row ────────────────────
            // Each row may be CASH_ON_HAND or BANK — all handled in a loop.
            foreach ($amountSources as $src) {
                $source   = $src['source'];   // CASH_ON_HAND | BANK
                $bankId   = $src['bank_id'] ?? null;
                $amount   = (float) $src['amount'];

                // Capture OBs before balance change
                $senderObCash  = (float) $customer->cash_balance;
                $senderObRtgs  = (float) $customer->rtgs_balance;
                $headObCash    = (float) $head->cash_balance;
                $headObRtgs    = (float) $head->rtgs_balance;
                $bankObBalance = 0.0;

                if ($transferToHead) {
                    // ── Head receives cash from customer ────────────────
                    if ($source === 'CASH_ON_HAND') {
                        $head->cash_balance = bcadd(
                            (string) $head->cash_balance,
                            (string) $amount,
                            4
                        );
                        $head->save();
                    } elseif ($source === 'BANK') {
                        // Lock the bank row before updating
                        $bank = BankDetail::where('bank_id', $bankId)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $bankObBalance = (float) $bank->ledger_balance;

                        $bank->ledger_balance = bcadd(
                            (string) $bank->ledger_balance,
                            (string) $amount,
                            4
                        );
                        $bank->save();
                    }
                }

                // ── 2. Save amount source row ────────────────────────────
                $amtSource = CashToGoldAmountSource::create([
                    'cash_to_gold_id' => $record->cash_to_gold_id,
                    'souce_type'      => $source,
                    'bank_id'         => $bankId,
                    'amount'          => $amount,
                    'added_at'        => now(),
                ]);

                // ── 4. Create cash_txn_details entry ────────────────────
                // sender = customer (pays), recipient = head (receives)
                $txn = CashTxnDetail::create([
                    'type'                  => 'CASH_TO_GOLD',
                    'sender_id'             => $data['customer_id'],
                    'recipient_id'          => $data['head_id'],
                    'amount'                => $amount,
                    'payment_method'        => $source,
                    'bank_account_id'       => $source === 'BANK' ? $bankId : null,
                    'sender_opening_cash'   => $senderObCash,
                    'sender_opening_rtgs'   => $senderObRtgs,
                    'recipient_opening_cash'=> $headObCash,
                    'recipient_opening_rtgs'=> $headObRtgs,
                    'sender_closing_cash'   => (float) $customer->cash_balance,
                    'sender_closing_rtgs'   => (float) $customer->rtgs_balance,
                    'recipient_closing_cash'=> (float) $head->cash_balance,
                    'recipient_closing_rtgs'=> (float) $head->rtgs_balance,
                    'remarks'               => 'CASH_TO_GOLD | '
                                              . $data['purity'] . ' * '
                                              . $data['per_gram_cash'] . ' = '
                                              . $data['total_cash'],
                    'cash_to_gold_id'       => $record->cash_to_gold_id,
                    'added_by'              => $addedBy,
                ]);

                // Back-fill cash_txn_id into the amount source
                $amtSource->cash_txn_id = $txn->txn_id;
                $amtSource->save();
            }

            // ── 5. Create billing_entries (OB snapshot) ──────────────────────
            $billing = BillingEntry::create([
                'type'          => 'IN',
                'head_id'       => $data['head_id'],
                'user_id'       => $data['customer_id'],
                'ob_purity'     => $customerObPurity,
                'ob_grams'      => $customerObGrams,
                'from_ob_purity'=> $headObPurity,
                'from_ob_grams' => $headObGrams,
                'remarks'       => $data['purity'] . ' * ' . $data['per_gram_cash'] . ' = ' . $data['total_cash'],
                'added_at'      => now(),
            ]);

            // ── 6. Create stock_details (gold stock IN) ──────────────────────
            $stock = StockDetails::create([
                'item_id'    => $data['item_id'],
                'given_by'   => $data['customer_id'],  // customer gives gold
                'given_to'   => $data['head_id'],       // head receives gold
                'stock_type' => 'IN',
                'entry_type' => 'CashToGold',
                'grams'      => $data['total_grams'],
                'touch'      => $data['touch'],
                'purity'     => $data['purity'],
                'balance'    => $data['purity'],
                'bill_id'    => $billing->bill_id,
                'remarks'    => 'CASH_TO_GOLD',
                'added_by'   => $addedBy,
                'added_at'   => now(),
            ]);

            // ── Update head's gold balances (grams & purity) ─────────────────
            $this->updateUserItemBalance(
                $data['head_id'],
                $data['item_id'],
                (string) $data['total_grams'],
                (string) $data['purity']
            );

            // ── Update customer's gold balances (loses gold) ─────────────────
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

            // Refresh head and customer after updates
            $head->refresh();
            $customer->refresh();


            // ── Update billing_entries with CB (closing balance) ─────────────
            $billing->cb_purity      = (float) $customer->purity_grand_total;
            $billing->cb_grams       = (float) $customer->grams_grand_total;
            $billing->from_cb_purity = (float) $head->purity_grand_total;
            $billing->from_cb_grams  = (float) $head->grams_grand_total;
            $billing->save();

            // ── 7. Back-fill stock_id into cash_to_gold ──────────────────────
            $record->stock_id = $stock->stock_id;
            $record->save();

            return $record->load(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item']);
        });
    }

    /**
     * List Cash To Gold records with filters.
     */
    public function index(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = CashToGold::where('type', 'CUSTOMER')
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
     * Find a single Cash To Gold record.
     */
    public function find(int $id): CashToGold
    {
        return CashToGold::where('type', 'CUSTOMER')
            ->with(['amountSources', 'cashTxnDetails', 'head', 'customer', 'item', 'stock'])
            ->findOrFail($id);
    }

    /**
     * Delete Cash To Gold record and reverse all balance changes.
     */
    public function delete(int $id, int $deletedBy): bool
    {
        return DB::transaction(function () use ($id) {

            $record = CashToGold::where('type', 'CUSTOMER')
                ->with(['amountSources'])
                ->lockForUpdate()
                ->findOrFail($id);

            $head     = UserDetail::where('user_id', $record->head_id)->lockForUpdate()->firstOrFail();
            $customer = UserDetail::where('user_id', $record->customer_id)->lockForUpdate()->firstOrFail();

            // Reverse each amount source balance
            foreach ($record->amountSources as $src) {
                if ($src->souce_type === 'CASH_ON_HAND') {
                    $head->cash_balance = bcsub(
                        (string) $head->cash_balance,
                        (string) $src->amount,
                        4
                    );
                    $head->save();
                } elseif ($src->souce_type === 'BANK' && $src->bank_id) {
                    $bank = BankDetail::where('bank_id', $src->bank_id)->lockForUpdate()->first();
                    if ($bank) {
                        $bank->ledger_balance = bcsub(
                            (string) $bank->ledger_balance,
                            (string) $src->amount,
                            4
                        );
                        $bank->save();
                    }
                }
            }

            // Reverse gold balance on head
            $this->updateUserItemBalance(
                $record->head_id,
                $record->item_id,
                '-' . $record->total_grams,
                '-' . $record->purity
            );

            // Soft-delete linked records
            $record->cashTxnDetails()->delete();
            $record->amountSources()->delete();

            // Delete stock entry
            if ($record->stock_id) {
                StockDetails::where('stock_id', $record->stock_id)->delete();
            }

            $record->delete();

            return true;
        });
    }
}
