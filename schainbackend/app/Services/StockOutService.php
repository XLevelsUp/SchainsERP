<?php

namespace App\Services;

use App\Models\OverAllBill;
use App\Models\BillingEntry;
use App\Models\StockDetails;
use App\Models\ItemChangeHistory;
use App\Models\GmsHistory;
use App\Models\NumericWastage;
use App\Models\CashTxnDetail;
use App\Models\GoldConversion;
use App\Models\GoldConversionAlloy;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StockOutService extends BaseStockService
{
    /**
     * 1. Process normal stock outward transaction (New Out) - supports dynamic arrays
     */
    public function createStockOut(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($data['given_by']);
            $givenTo = UserDetail::findOrFail($data['given_to']);
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
                'type' => 'OUT',
                'head_id' => $addedBy,
                'user_id' => $givenTo->user_id,
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
                $noOfPcs = $itemData['no_of_pcs'] ?? null;
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

                // Create StockDetails row
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NORMAL',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'no_of_pcs' => $noOfPcs,
                    'touch' => $touch,
                    'purity' => $purity,
                    'remarks' => $remarks,
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wasteTotal,
                    'waste_value' => $wasteValue,
                    'bill_id' => $billingEntry->id, // OverAllBill id
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

                // Update user balances: deduct from sender, add to receiver
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

                // Save CB snapshot directly on model using saveQuietly to prevent observer loops
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
     * 2. Swaps stock weight from one item category to another (Item Change) - supports dynamic arrays
     */
    public function createItemChange(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $user = UserDetail::findOrFail($data['user_id']);
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            $createdHistories = [];

            foreach ($data['items'] as $itemData) {
                $parentStock = StockDetails::findOrFail($itemData['stock_in_id']);
                $grams = $itemData['grams'];
                $fromTouch = $itemData['from_touch'];
                $reqTouch = $itemData['req_touch'];
                $fromItemId = $itemData['from_item_id'];
                $toItemId = $itemData['to_item_id'];
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;

                // Purity calculations (or use frontend purity)
                $totalPurity = $itemData['purity'] ?? $this->div($this->mul($grams, $reqTouch), '100');

                // Snapshot OB
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($user->user_id, $fromItemId, $toItemId, 'ob'),
                    ]
                ];

                // Parent stock balance before change
                $stockInOb = $parentStock->balance;
                $stockInCb = $this->sub($parentStock->balance, $grams);
                $stockInObPurity = $this->div($this->mul($stockInOb, $parentStock->touch), '100');
                $stockInCbPurity = $this->div($this->mul($stockInCb, $parentStock->touch), '100');

                // 1. Create OUT stock details record (for from_item_id)
                $outStock = StockDetails::create([
                    'item_id' => $fromItemId,
                    'given_by' => $user->user_id,
                    'given_to' => $user->user_id,
                    'type' => 'ITEMCHANGE',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'touch' => $fromTouch,
                    'purity' => $this->div($this->mul($grams, $fromTouch), '100'),
                    'remarks' => $remarks ?? ("Item Change from: " . $fromItemId),
                    'item_remarks' => $itemRemarks,
                    'balance' => $grams,
                    'stock_in_id' => $parentStock->stock_id,
                    'added_by' => $addedBy,
                    'to_item_id' => $toItemId,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_purity'] ?? 0,
                    'stock_in_ob' => $stockInOb,
                    'stock_in_cb' => $stockInCb,
                    'stock_in_ob_purity' => $stockInObPurity,
                    'stock_in_cb_purity' => $stockInCbPurity,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // 2. Create IN stock details record (for to_item_id)
                $inStock = StockDetails::create([
                    'item_id' => $toItemId,
                    'given_by' => $user->user_id,
                    'given_to' => $user->user_id,
                    'type' => 'ITEMCHANGE',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'IN',
                    'grams' => $grams,
                    'touch' => $reqTouch,
                    'purity' => $totalPurity,
                    'remarks' => $remarks ?? ("Item Change to: " . $toItemId),
                    'item_remarks' => $itemRemarks,
                    'balance' => $grams,
                    'stock_in_id' => $parentStock->stock_id,
                    'added_by' => $addedBy,
                    'to_item_id' => $fromItemId,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_purity'] ?? 0,
                    'stock_in_ob' => $stockInOb,
                    'stock_in_cb' => $stockInCb,
                    'stock_in_ob_purity' => $stockInObPurity,
                    'stock_in_cb_purity' => $stockInCbPurity,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // Deduct balance from parent stock details record
                $parentStock->balance = $this->sub($parentStock->balance, $grams);
                if (bccomp((string)$parentStock->balance, '0', 4) <= 0) {
                    $parentStock->is_completed = true;
                }
                $parentStock->save();

                // Update item-specific user mappings balances
                // OUT mapping update
                $this->updateUserItemBalance($user->user_id, $fromItemId, "-$grams", "-" . $this->div($this->mul($grams, $fromTouch), '100'));
                // IN mapping update
                $this->updateUserItemBalance($user->user_id, $toItemId, $grams, $totalPurity);

                // Snapshot CB
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($user->user_id, $fromItemId, $toItemId, 'cb'),
                    ]
                ];

                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                // Save CB snapshot directly on model using saveQuietly
                $outStock->obcb_details = $snapshot;
                $outStock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $outStock->given_to_item_grams_cb = $cbSnapshot['given_by_details']['cb']['to_item_details']['cb_grams'] ?? 0;
                $outStock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $outStock->given_to_item_purity_cb = $cbSnapshot['given_by_details']['cb']['to_item_details']['cb_purity'] ?? 0;
                $outStock->saveQuietly();

                $inStock->obcb_details = $snapshot;
                $inStock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $inStock->given_to_item_grams_cb = $cbSnapshot['given_by_details']['cb']['to_item_details']['cb_grams'] ?? 0;
                $inStock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $inStock->given_to_item_purity_cb = $cbSnapshot['given_by_details']['cb']['to_item_details']['cb_purity'] ?? 0;
                $inStock->saveQuietly();

                // Record Item Change History
                $history = ItemChangeHistory::create([
                    'from_item_id' => $fromItemId,
                    'to_item_id' => $toItemId,
                    'grams' => $grams,
                    'from_touch' => $fromTouch,
                    'req_touch' => $reqTouch,
                    'total' => $totalPurity,
                    'change_type' => 'ITEMCHANGE',
                    'out_stock_id' => $outStock->stock_id,
                    'in_stock_id' => $inStock->stock_id,
                    'added_at' => $addedAt,
                ]);

                $createdHistories[] = $history;
            }

            // Invalidate user balance cache
            Cache::forget("user:{$user->user_id}:balances");

            return $createdHistories;
        });
    }

    /**
     * 3. Item conversion submodule (Item Conversion) - supports dynamic arrays
     */
    public function createItemConversion(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $user = UserDetail::findOrFail($data['user_id']);
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            $createdConversions = [];

            // Billing entry
            $overallBill = OverAllBill::create([
                'is_active' => true,
                'is_cash_updated' => false,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            $billingEntry = BillingEntry::create([
                'over_all_bill_id' => $overallBill->id,
                'type' => 'OUT',
                'head_id' => $addedBy,
                'user_id' => $addedBy,
                'ob_purity' => 0,
                'ob_grams' => 0,
                'from_ob_purity' => 0,
                'from_ob_grams' => 0,
                'added_at' => $addedAt,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            foreach ($data['items'] as $itemData) {
                $stockInId = $itemData['stock_in_id'] ?? null;
                $sourceItemId = $itemData['source_item_id'];
                $targetItemId = $itemData['target_item_id'];
                $sourceGrams = $itemData['source_grams'];
                $sourceTouch = $itemData['source_touch'];
                $targetTouch = $itemData['target_touch'];
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;

                // formula: total (converted_grams) = source_grams * (source_touch / target_touch)
                $convertedGrams = $this->div($this->mul($sourceGrams, $sourceTouch), $targetTouch);
                $outPurity = $itemData['purity'] ?? $this->div($this->mul($sourceGrams, $sourceTouch), '100');
                $inPurity = $itemData['purity'] ?? $this->div($this->mul($convertedGrams, $targetTouch), '100');

                // Snapshot OB
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($user->user_id, $sourceItemId, $targetItemId, 'ob'),
                    ]
                ];

                $stockInOb = null;
                $stockInCb = null;
                $stockInObPurity = null;
                $stockInCbPurity = null;
                $parentStock = null;

                if ($stockInId) {
                    $parentStock = StockDetails::find($stockInId);
                    if ($parentStock) {
                        $stockInOb = $parentStock->balance;
                        $stockInCb = $this->sub($parentStock->balance, $sourceGrams);
                        $stockInObPurity = $this->div($this->mul($stockInOb, $parentStock->touch), '100');
                        $stockInCbPurity = $this->div($this->mul($stockInCb, $parentStock->touch), '100');
                    }
                }

                // 1. Create OUT stock details record
                $outStock = StockDetails::create([
                    'item_id' => $sourceItemId,
                    'given_by' => $user->user_id,
                    'given_to' => $user->user_id,
                    'type' => 'ITEMCONVERSION',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $sourceGrams,
                    'touch' => $sourceTouch,
                    'purity' => $outPurity,
                    'remarks' => $remarks ?? ("Item Conversion from: " . $sourceItemId),
                    'item_remarks' => $itemRemarks,
                    'balance' => $sourceGrams,
                    'stock_in_id' => $stockInId,
                    'bill_id' => $billingEntry->bill_id,
                    'added_by' => $addedBy,
                    'to_item_id' => $targetItemId,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_purity'] ?? 0,
                    'stock_in_ob' => $stockInOb,
                    'stock_in_cb' => $stockInCb,
                    'stock_in_ob_purity' => $stockInObPurity,
                    'stock_in_cb_purity' => $stockInCbPurity,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // 2. Create IN stock details record
                $inStock = StockDetails::create([
                    'item_id' => $targetItemId,
                    'given_by' => $user->user_id,
                    'given_to' => $user->user_id,
                    'type' => 'ITEMCONVERSION',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'IN',
                    'grams' => $convertedGrams,
                    'touch' => $targetTouch,
                    'purity' => $inPurity,
                    'remarks' => $remarks ?? ("Item Conversion to: " . $targetItemId),
                    'item_remarks' => $itemRemarks,
                    'balance' => $convertedGrams,
                    'stock_in_id' => $stockInId,
                    'bill_id' => $billingEntry->bill_id,
                    'added_by' => $addedBy,
                    'to_item_id' => $sourceItemId,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_by_details']['ob']['to_item_details']['ob_purity'] ?? 0,
                    'stock_in_ob' => $stockInOb,
                    'stock_in_cb' => $stockInCb,
                    'stock_in_ob_purity' => $stockInObPurity,
                    'stock_in_cb_purity' => $stockInCbPurity,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                if ($parentStock) {
                    $parentStock->balance = $this->sub($parentStock->balance, $sourceGrams);
                    if (bccomp((string)$parentStock->balance, '0', 4) <= 0) {
                        $parentStock->is_completed = true;
                    }
                    $parentStock->save();
                }

                // Update mapping balances
                $this->updateUserItemBalance($user->user_id, $sourceItemId, "-$sourceGrams", "-$outPurity");
                $this->updateUserItemBalance($user->user_id, $targetItemId, $convertedGrams, $inPurity);

                // Snapshot CB
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($user->user_id, $sourceItemId, $targetItemId, 'cb'),
                    ]
                ];

                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                // Save CB snapshots and direct CB columns
                $outStock->obcb_details = $snapshot;
                $outStock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $outStock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_grams'] ?? 0;
                $outStock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $outStock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_purity'] ?? 0;
                $outStock->saveQuietly();

                $inStock->obcb_details = $snapshot;
                $inStock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $inStock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_grams'] ?? 0;
                $inStock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $inStock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_purity'] ?? 0;
                $inStock->saveQuietly();

                // Save Gold Conversion record
                $conversion = GoldConversion::create([
                    'source_item_id' => $sourceItemId,
                    'target_item_id' => $targetItemId,
                    'source_grams' => $sourceGrams,
                    'source_touch' => $sourceTouch,
                    'target_touch' => $targetTouch,
                    'converted_grams' => $convertedGrams,
                    'out_stock_id' => $outStock->stock_id,
                    'in_stock_id' => $inStock->stock_id,
                    'billing_entry_id' => $billingEntry->bill_id,
                    'added_at' => $addedAt,
                ]);

                // Create alloy records if present
                if (isset($itemData['alloys']) && is_array($itemData['alloys'])) {
                    foreach ($itemData['alloys'] as $alloyData) {
                        GoldConversionAlloy::create([
                            'conversion_id' => $conversion->id,
                            'alloy_item_id' => $alloyData['alloy_item_id'],
                            'alloy_percentage' => $alloyData['alloy_percentage'],
                            'alloy_grams' => $alloyData['alloy_grams'],
                            'created_at' => $addedAt,
                        ]);
                    }
                }

                $createdConversions[] = $conversion;
            }

            Cache::forget("user:{$user->user_id}:balances");

            return $createdConversions;
        });
    }

    /**
     * 4. Goldsmith Outward transaction (GMS Out) - outsourced to worker - supports dynamic arrays
     */
    public function createGmsOut(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($data['given_by'] ?? $addedBy); // head/creator
            $givenTo = UserDetail::findOrFail($data['given_to']); // goldsmith worker
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
                $noOfPcs = $itemData['no_of_pcs'] ?? null;
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

                // Create StockDetails row
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'GMS',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'no_of_pcs' => $noOfPcs,
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

                // Create GmsHistory row
                $gmsHistory = GmsHistory::create([
                    'item_id' => $itemId,
                    'grams' => $grams,
                    'stone' => $stone,
                    'thread' => $thread,
                    'mtouch' => $mtouch,
                    'mtouch_wastage' => $mtouchWastage,
                    'wastage' => $wastage,
                    'hall_mark' => $hallMark,
                    'total' => $purity,
                    'gms_type' => 'OUT',
                    'gms_stock_out_id' => $stock->stock_id,
                    'added_at' => $itemAddedAt,
                ]);

                // Update balances: deduct head, add worker
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

                // Save CB snapshot directly on model and CB columns
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
     * 5. Swaps out piece-based wastage to a worker (Numeric Wastage Out) - supports dynamic arrays
     */
    public function createNumericWaste(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($data['given_by'] ?? $addedBy);
            $givenTo = UserDetail::findOrFail($data['given_to']);
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
                $amount = $itemData['amount'] ?? $this->mul($noOfPcs, $amountPcs);

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

                // Create StockDetails row
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NUMERICWASTE',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'no_of_pcs' => $noOfPcs,
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
                    $cashAmount = $this->mul($noOfPcs, $amountPcs);
                    
                    $openingAccountBalance = $givenBy->rak_cash_balance;
                    $openingUserBalance = $givenTo->rak_cash_balance;

                    $cashTxn = CashTxnDetail::create([
                        'type' => 'EXPENSE',
                        'recipient_id' => $givenTo->user_id, // worker receives cash
                        'sender_id' => $givenBy->user_id, // head admin pays cash
                        'amount' => $cashAmount,
                        'sender_opening_cash' => $openingAccountBalance, // sender (head) cash balance
                        'recipient_opening_cash' => $openingUserBalance, // receiver (worker) cash balance
                        'payment_method' => 'CASH_ON_HAND',
                        'remarks' => "NUMERIC_WASTAGE EXPENSE (ID : {$stock->stock_id})",
                        'added_by' => $addedBy,
                    ]);

                    $cashTxnId = $cashTxn->txn_id;

                    // Deduct from head (givenBy)
                    $givenBy->rak_cash_balance = $this->sub($givenBy->rak_cash_balance, $cashAmount);
                    // Add to worker (givenTo)
                    $givenTo->rak_cash_balance = $this->add($givenTo->rak_cash_balance, $cashAmount);
                }

                // Update balances: deduct head, add worker
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

                // Save CB snapshot and CB columns
                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $nw = NumericWastage::create([
                    'item_id' => $itemId,
                    'grams' => $grams,
                    'touch' => $touch,
                    'no_of_pcs' => $noOfPcs,
                    'wastage_value' => $wastageValue,
                    'wastage_total' => $wastageTotal,
                    'type' => 'OUT',
                    'stock_id' => $stock->stock_id,
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

    /**
     * 6. Hide specific stock details and their parent
     */
    public function hideStocks(array $stockIds): void
    {
        DB::transaction(function () use ($stockIds) {
            foreach ($stockIds as $id) {
                $stock = StockDetails::findOrFail($id);
                $stock->is_hided = true;
                $stock->save();

                // If parent stock_in_id is linked, hide it as well
                if ($stock->stock_in_id) {
                    $parent = StockDetails::find($stock->stock_in_id);
                    if ($parent) {
                        $parent->is_hided = true;
                        $parent->save();
                    }
                }
            }
        });
    }

    /**
     * 7. Cash / RTGS Transfer (Cash Out)
     */
    public function createCashOut(array $data, int $addedBy): CashTxnDetail
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($addedBy); // head/sender
            $givenTo = UserDetail::findOrFail($data['given_to']); // employee/receiver
            $amount = $data['amount'];
            $remarks = $data['remarks'] ?? null;

            $openingAccountBalance = $givenBy->rak_cash_balance;
            $openingUserBalance = $givenTo->rak_cash_balance;

            // Create cash transaction record
            $cashTxn = CashTxnDetail::create([
                'type' => 'EXPENSE',
                'recipient_id' => $givenTo->user_id,
                'sender_id' => $givenBy->user_id,
                'amount' => $amount,
                'sender_opening_cash' => $openingAccountBalance,
                'recipient_opening_cash' => $openingUserBalance,
                'payment_method' => 'CASH_ON_HAND',
                'remarks' => $remarks,
                'added_by' => $addedBy,
            ]);

            // Deduct cash from sender
            $givenBy->rak_cash_balance = $this->sub($givenBy->rak_cash_balance, $amount);
            $givenBy->save();

            // Add cash to receiver
            $givenTo->rak_cash_balance = $this->add($givenTo->rak_cash_balance, $amount);
            $givenTo->save();

            // Invalidate user balance cache for both
            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $cashTxn;
        });
    }
}
