<?php

namespace App\Services;

use App\Models\OverAllBill;
use App\Models\BillingEntry;
use App\Models\StockInDetail;
use App\Models\GmsInHistory;
use App\Models\NumericWastageIn;
use App\Models\CashTxnDetail;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StockInService extends BaseStockService
{
    /**
     * 1. Process returns/inward gold (New In) - supports dynamic arrays
     */
    public function createStockIn(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::where('user_id', $data['given_by'])->lockForUpdate()->firstOrFail(); // worker
            $givenTo = UserDetail::where('user_id', $data['given_to'])->lockForUpdate()->firstOrFail(); // head admin
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            $createdStocks = [];

            // Billing Entry
            $overallBill = OverAllBill::create([
                'is_active' => true,
                'is_cash_updated' => false,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            $billingEntry = BillingEntry::create([
                'over_all_bill_id' => $overallBill->id,
                'type' => 'IN',
                'head_id' => $addedBy,
                'user_id' => $givenBy->user_id,
                'ob_purity' => 0,
                'ob_grams' => 0,
                'from_ob_purity' => 0,
                'from_ob_grams' => 0,
                'added_at' => $addedAt,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $grams = $itemData['grams'];
                $touch = $itemData['touch'];
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;
                $wasteId = $itemData['waste_id'] ?? null;
                $wasteTotal = $itemData['waste_total'] ?? 0;
                $itemAddedAt = isset($itemData['added_at']) ? \Illuminate\Support\Carbon::parse($itemData['added_at']) : $addedAt;

                // waste_value = grams * waste_total / 100
                $wasteValue = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->div($this->mul($grams, $wasteTotal), '100');

                // purity = (grams * touch / 100) + waste_value
                $purity = $itemData['purity'] ?? $this->add($this->div($this->mul($grams, $touch), '100'), $wasteValue);

                // calculate grams changes based on roles (head does not add wastage to their balance)
                $givenbygrams = $giventograms = $grams;
                if ($wasteTotal) {
                    if ($givenBy->role_id != 1) { // Assuming role_id 1 is HEAD/Admin
                        $givenbygrams = $this->add($grams, $wasteValue);
                    }
                    if ($givenTo->role_id != 1) {
                        $giventograms = $this->add($grams, $wasteValue);
                    }
                }

                // Snapshot OB details
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create StockInDetail row
                $stock = StockInDetail::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NORMAL',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'IN',
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'remarks' => $remarks,
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wasteTotal,
                    'waste_value' => $wasteValue,
                    'bill_id' => $billingEntry->id, // OverAllBill id
                    'balance' => $grams,
                    'stock_in_id' => $itemData['stock_in_id'] ?? null,
                    'added_by' => $addedBy,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $itemAddedAt,
                    'created_at' => $itemAddedAt,
                    'updated_at' => $itemAddedAt,
                ]);

                // Update user balances: deduct from sender (worker), add to receiver (head admin)
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $givenbygrams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$givenbygrams", "-$purity");

                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $giventograms);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $giventograms, $purity);

                // Snapshot CB details
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'cb'),
                    ],
                    'given_to_details' => [
                        'cb' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'cb'),
                    ]
                ];

                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                // Save CB snapshot directly on model using saveQuietly
                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $createdStocks[] = $stock;
            }

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdStocks;
        }, 5);
    }

    /**
     * 2. Process GMS Inward (GMS In) - goldsmith returning gold - supports dynamic arrays
     */
    public function createGmsIn(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::where('user_id', $data['given_by'])->lockForUpdate()->firstOrFail(); // worker
            $givenTo = UserDetail::where('user_id', $data['given_to'])->lockForUpdate()->firstOrFail(); // head admin
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            $createdGms = [];

            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $grams = $itemData['grams'];
                $stone = $itemData['stone'] ?? 0;
                $thread = $itemData['thread'] ?? 0;
                $wastage = $itemData['wastage'] ?? 0;
                $hallMark = $itemData['hall_mark'];
                $mtouch = $itemData['mtouch'] ?? 0;
                $mtouchWastage = $itemData['mtouch_wastage'] ?? 0;
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;
                $itemAddedAt = isset($itemData['added_at']) ? \Illuminate\Support\Carbon::parse($itemData['added_at']) : $addedAt;

                // Net grams = grams - stone - thread
                $netGrams = $this->sub($grams, $this->add($stone, $thread));
                // Wastage grams = netGrams * (wastage / 100) (or use frontend wValue/waste_value)
                $wastageGrams = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->div($this->mul($netGrams, $wastage), '100');
                // Calculated Purity = (netGrams + wastageGrams) * (hallMark / 100) (or use frontend purity)
                $purity = $itemData['purity'] ?? $this->div($this->mul($this->add($netGrams, $wastageGrams), $hallMark), '100');

                // Snapshot OB details
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create StockInDetail row
                $stock = StockInDetail::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'GMS',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'IN',
                    'grams' => $grams,
                    'touch' => $hallMark,
                    'purity' => $purity,
                    'remarks' => $remarks,
                    'item_remarks' => $itemRemarks,
                    'waste_total' => $wastage,
                    'waste_value' => $wastageGrams,
                    'mtouch' => $mtouch,
                    'gms_mtouch' => $mtouch,
                    'gms_mthouch_wastage' => $mtouchWastage,
                    'balance' => $grams,
                    'added_by' => $addedBy,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $itemAddedAt,
                    'created_at' => $itemAddedAt,
                    'updated_at' => $itemAddedAt,
                ]);

                // Create GmsInHistory row
                $gmsHistory = GmsInHistory::create([
                    'item_id' => $itemId,
                    'grams' => $grams,
                    'stone' => $stone,
                    'thread' => $thread,
                    'mtouch' => $mtouch,
                    'mtouch_wastage' => $mtouchWastage,
                    'wastage' => $wastage,
                    'hall_mark' => $hallMark,
                    'total' => $purity,
                    'gms_type' => 'IN',
                    'gms_stock_in_id' => $stock->stock_in_detail_id,
                    'added_at' => $itemAddedAt,
                ]);

                // Update balances: deduct worker, add head
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $grams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$grams", "-$purity");

                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $grams);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $grams, $purity);

                // Snapshot CB details
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'cb'),
                    ],
                    'given_to_details' => [
                        'cb' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'cb'),
                    ]
                ];

                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $createdGms[] = $gmsHistory;
            }

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdGms;
        }, 5);
    }

    /**
     * 3. Process Numeric Wastage Inward - worker returning items - supports dynamic arrays
     */
    public function createNumericWasteIn(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::where('user_id', $data['given_by'])->lockForUpdate()->firstOrFail(); // worker
            $givenTo = UserDetail::where('user_id', $data['given_to'])->lockForUpdate()->firstOrFail(); // head admin
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            $createdWastages = [];

            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $grams = $itemData['grams'];
                $touch = $itemData['touch'];
                $noOfPcs = $itemData['no_of_pcs'];
                $amountPcs = $itemData['amount_pcs'] ?? 0;
                $wasteId = $itemData['waste_id'] ?? null;
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;
                $itemAddedAt = isset($itemData['added_at']) ? \Illuminate\Support\Carbon::parse($itemData['added_at']) : $addedAt;

                $wastageValue = $itemData['waste_total'] ?? 0; // wastage per pc
                // Calculated WValue (wastage total weight)
                $wastageTotal = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->mul($noOfPcs, $wastageValue);
                $amount = $itemData['amount'] ?? bcmul((string)$noOfPcs, (string)$amountPcs, 4);

                // purity = (grams + wastageTotal) * (touch / 100) (or use frontend purity)
                $purity = $itemData['purity'] ?? $this->div($this->mul($this->add($grams, $wastageTotal), $touch), '100');

                // Snapshot OB details
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create StockInDetail row
                $stock = StockInDetail::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NUMERICWASTE',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'IN',
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'remarks' => $remarks,
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wastageValue,
                    'waste_value' => $wastageTotal,
                    'mtouch' => $amountPcs,
                    'balance' => $grams,
                    'added_by' => $addedBy,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $itemAddedAt,
                    'created_at' => $itemAddedAt,
                    'updated_at' => $itemAddedAt,
                ]);

                // Handle Cash Transaction mapping if enabled
                $cashTxnId = null;
                if ($amountPcs > 0) {
                    $cashAmount = bcmul((string)$noOfPcs, (string)$amountPcs, 4);
                    
                    $openingAccountBalance = $givenBy->rak_cash_balance;
                    $openingUserBalance = $givenTo->rak_cash_balance;

                    $cashTxn = CashTxnDetail::create([
                        'type' => 'INCOME',
                        'given_to' => $givenTo->user_id, // head admin receives cash
                        'given_by' => $givenBy->user_id, // worker pays cash
                        'amount' => $cashAmount,
                        'opening_account_balance' => $openingAccountBalance, // sender (worker) cash balance
                        'opening_user_balance' => $openingUserBalance, // receiver (head) cash balance
                        'souce_type' => 'CASH_ON_HAND',
                        'remarks' => "NUMERIC_WASTAGE INCOME (ID : {$stock->stock_in_detail_id})",
                        'added_by' => $addedBy,
                    ]);

                    $cashTxnId = $cashTxn->txn_id;

                    // Deduct from worker (givenBy)
                    $givenBy->rak_cash_balance = $this->sub($givenBy->rak_cash_balance, $cashAmount);
                    // Add to head (givenTo)
                    $givenTo->rak_cash_balance = $this->add($givenTo->rak_cash_balance, $cashAmount);
                }

                // Update balances: deduct worker, add head
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $grams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$grams", "-$purity");

                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $grams);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $grams, $purity);

                // Snapshot CB details
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'cb'),
                    ],
                    'given_to_details' => [
                        'cb' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'cb'),
                    ]
                ];

                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $nw = NumericWastageIn::create([
                    'item_id' => $itemId,
                    'grams' => $grams,
                    'touch' => $touch,
                    'no_of_pcs' => $noOfPcs,
                    'wastage_id' => $wasteId,
                    'wastage_value' => $wastageValue,
                    'wastage_total' => $wastageTotal,
                    'type' => 'IN',
                    'stock_in_detail_id' => $stock->stock_in_detail_id,
                    'amount' => $amount,
                    'cash_txn_id' => $cashTxnId,
                    'added_at' => $itemAddedAt,
                ]);

                $createdWastages[] = $nw;
            }

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdWastages;
        }, 5);
    }
}
