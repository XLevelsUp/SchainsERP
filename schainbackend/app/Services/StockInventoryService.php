<?php

namespace App\Services;

use App\Models\OverAllBill;
use App\Models\BillingEntry;
use App\Models\StockDetails;
use App\Models\ItemChangeHistory;
use App\Models\GmsHistory;
use App\Models\NumericWastage;
use App\Models\CashTxn;
use App\Models\GoldConversion;
use App\Models\GoldConversionAlloy;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StockInventoryService
{
    /**
     * Perform BCMath addition with 4 decimal places.
     */
    protected function add($a, $b): string
    {
        return bcadd((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath subtraction with 4 decimal places.
     */
    protected function sub($a, $b): string
    {
        return bcsub((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath multiplication with 4 decimal places.
     */
    protected function mul($a, $b): string
    {
        return bcmul((string)($a ?? 0), (string)($b ?? 0), 4);
    }

    /**
     * Perform BCMath division with 4 decimal places.
     */
    protected function div($a, $b): string
    {
        return bcdiv((string)($a ?? 0), (string)($b ?? 1), 4);
    }

    /**
     * Capture a snapshot of user balances for OB/CB auditing.
     */
    protected function buildUserItemDetails(?int $userId, ?int $itemId, ?int $toItemId, string $prefix = 'ob'): array
    {
        $details = [
            'item_details' => [],
            'to_item_details' => [],
            'tot_' . $prefix . '_grams' => '0.0000',
            'tot_' . $prefix . '_purity' => '0.0000',
        ];

        if (!$userId) {
            return $details;
        }

        $user = UserDetail::find($userId);
        if (!$user) {
            return $details;
        }

        $item = UsersItemsMapping::where('item_id', $itemId)->where('user_id', $userId)->first();
        if ($item) {
            $details['item_details'] = [
                $prefix . '_grams' => number_format((float)$item->item_grams_total, 4, '.', ''),
                $prefix . '_purity' => number_format((float)$item->item_purity_total, 4, '.', ''),
            ];
        }

        if ($toItemId && $toItemId !== $itemId) {
            $toItem = UsersItemsMapping::where('item_id', $toItemId)->where('user_id', $userId)->first();
            if ($toItem) {
                $details['to_item_details'] = [
                    $prefix . '_grams' => number_format((float)$toItem->item_grams_total, 4, '.', ''),
                    $prefix . '_purity' => number_format((float)$toItem->item_purity_total, 4, '.', ''),
                ];
            }
        }

        $details['tot_' . $prefix . '_grams'] = number_format((float)$user->grams_grand_total, 4, '.', '');
        $details['tot_' . $prefix . '_purity'] = number_format((float)$user->purity_grand_total, 4, '.', '');

        return $details;
    }

    /**
     * Update mappings balance helper.
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
     * 1. Create Stock Outward Transaction (New Out)
     */
    public function createStockOut(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($data['given_by']);
            $givenTo = UserDetail::findOrFail($data['given_to']);

            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            // 1. Create Overall Bill and Billing Entries
            $overallBill = OverAllBill::create([
                'is_active' => true,
                'is_cash_updated' => false,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            // Snapshot OB
            $headObPurity = $givenBy->purity_grand_total;
            $headObGrams = $givenBy->grams_grand_total;
            $userObPurity = $givenTo->purity_grand_total;
            $userObGrams = $givenTo->grams_grand_total;

            $billingEntry = BillingEntry::create([
                'over_all_bill_id' => $overallBill->id,
                'type' => 'OUT',
                'head_id' => $givenBy->user_id,
                'user_id' => $givenTo->user_id,
                'ob_purity' => $headObPurity,
                'ob_grams' => $headObGrams,
                'from_ob_purity' => $userObPurity,
                'from_ob_grams' => $userObGrams,
                'added_at' => $addedAt,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            $createdStocks = [];

            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'];
                $grams = $itemData['grams'];
                $touch = $itemData['touch'];
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;
                $wasteId = $itemData['waste_id'] ?? null;
                $wasteTotal = $itemData['waste_total'] ?? 0; // wastage percentage, e.g. 0.180
                $itemAddedAt = isset($itemData['added_at']) ? \Illuminate\Support\Carbon::parse($itemData['added_at']) : $addedAt;

                // waste_value = grams * waste_total / 100 (or use frontend wValue/waste_value)
                $wasteValue = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->div($this->mul($grams, $wasteTotal), '100');

                // purity = (grams * touch / 100) + waste_value (or use frontend purity)
                $purity = $itemData['purity'] ?? $this->add($this->div($this->mul($grams, $touch), '100'), $wasteValue);

                // Build OB snapshot
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create Stock Detail record (OUT)
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NORMAL',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'remarks' => $remarks,
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wasteTotal,
                    'waste_value' => $wasteValue,
                    'bill_id' => $billingEntry->bill_id,
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

                // Determine gram adjustments based on role (HEAD admin role ignores wastage addition for deduct, employee doesn't)
                $isGivenByHead = (strtoupper($givenBy->proff) === 'HEAD' || strtoupper($givenBy->role_id) === 'HEAD' || $givenBy->role_id == '1');
                $isGivenToHead = (strtoupper($givenTo->proff) === 'HEAD' || strtoupper($givenTo->role_id) === 'HEAD' || $givenTo->role_id == '1');

                $givenByGramsDeduct = $isGivenByHead ? $grams : $this->add($grams, $wasteValue);
                $givenToGramsAdd = $isGivenToHead ? $grams : $this->add($grams, $wasteValue);

                // Update Balances
                // 1. Deduct from givenBy
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $givenByGramsDeduct);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->last_txn_date = $addedAt;
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$givenByGramsDeduct", "-$purity");

                // 2. Add to givenTo
                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $givenToGramsAdd);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->last_txn_date = $addedAt;
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $givenToGramsAdd, $purity);

                // Build CB snapshot
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'cb'),
                    ],
                    'given_to_details' => [
                        'cb' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'cb'),
                    ]
                ];

                // Merge into single array
                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);

                // Save CB snapshot directly on model using updateQuietly to prevent infinite loops
                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $createdStocks[] = $stock;
            }

            // Update Billing entry CB
            $billingEntry->update([
                'cb_purity' => $givenBy->purity_grand_total,
                'cb_grams' => $givenBy->grams_grand_total,
                'from_cb_purity' => $givenTo->purity_grand_total,
                'from_cb_grams' => $givenTo->grams_grand_total,
            ]);

            return [
                'bill_id' => $billingEntry->bill_id,
                'stocks' => $createdStocks,
            ];
        });
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

                // Save CB snapshot directly on model using updateQuietly to prevent infinite loops
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
     * 3. Item conversion submodule - supports dynamic arrays
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
     * 4. Send raw gold to a goldsmith (GMS Out) - supports dynamic arrays
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
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;

                // Math: Net grams = grams - stone - thread
                $netGrams = $this->sub($grams, $this->add($stone, $thread));
                // Wastage grams = netGrams * (wastage / 100) (or use frontend wValue/waste_value)
                $wastageGrams = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->div($this->mul($netGrams, $wastage), '100');
                // Calculated Purity = (netGrams + wastageGrams) * (hallMark / 100) (or use frontend purity)
                $purity = $itemData['purity'] ?? $this->div($this->mul($this->add($netGrams, $wastageGrams), $hallMark), '100');

                // Snapshot OB
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create Stock details record (OUT)
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'GMS',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'touch' => $hallMark,
                    'purity' => $purity,
                    'remarks' => $remarks ?? "GMS OUT",
                    'item_remarks' => $itemRemarks,
                    'waste_total' => $wastageGrams,
                    'waste_value' => $wastage,
                    'mtouch' => $mtouch,
                    'gms_mtouch' => $mtouch,
                    'gms_mthouch_wastage' => $mtouchWastage,
                    'balance' => $grams,
                    'added_by' => $addedBy,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // Update user balances: deduct from head, add to worker
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $grams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$grams", "-$purity");

                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $grams);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $grams, $purity);

                // Snapshot CB
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

                // Create GMS History
                $gmsHistory = GmsHistory::create([
                    'item_id' => $itemId,
                    'grams' => $grams,
                    'stone' => $stone,
                    'thread' => $thread,
                    'wastage' => $wastage,
                    'hall_mark' => $hallMark,
                    'total' => $purity,
                    'gms_type' => 'OUT',
                    'gms_stock_out_id' => $stock->stock_id,
                    'added_at' => $addedAt,
                ]);

                $createdGms[] = $gmsHistory;
            }

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdGms;
        });
    }

    /**
     * 5. Numeric wastage out - supports dynamic arrays
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
                $wastageValue = $itemData['waste_total'] ?? 0; // wastage per pc in grams/unit
                // calculated WValue
                $wastageTotal = $itemData['waste_value'] ?? $itemData['wValue'] ?? $this->mul($noOfPcs, $wastageValue);
                $amount = $itemData['amount'] ?? $this->mul($noOfPcs, $amountPcs);
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;

                // purity = (grams + wastageTotal) * (touch / 100) (or use frontend purity)
                $purity = $itemData['purity'] ?? $this->div($this->mul($this->add($grams, $wastageTotal), $touch), '100');

                // Snapshot OB
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $itemId, null, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $itemId, null, 'ob'),
                    ]
                ];

                // Create Stock Details
                $stock = StockDetails::create([
                    'item_id' => $itemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => 'NUMERIC_WASTAGE',
                    'entry_type' => 'NORMAL',
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'remarks' => $remarks ?? "Numeric Wastage Out",
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wastageValue, // mapped to wastage_value
                    'waste_value' => $wastageTotal, // mapped to wastage_total
                    'mtouch' => $amount,
                    'balance' => $grams,
                    'added_by' => $addedBy,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // Update user balances: deduct from head, add to worker
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $grams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $itemId, "-$grams", "-$purity");

                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $grams);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $purity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $itemId, $grams, $purity);

                // Snapshot CB
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

                $cashTxnId = null;

                // If amount > 0, generate cash expense payout
                if (bccomp((string)$amount, '0', 4) > 0) {
                    $openingAccountBalance = $givenBy->rak_cash_balance;
                    $openingUserBalance = $givenTo->rak_cash_balance;

                    // Create Cash Txn details
                    $cashTxn = CashTxn::create([
                        'type' => 'EXPENSE',
                        'given_to' => $givenTo->user_id,
                        'given_by' => $givenBy->user_id,
                        'amount' => $amount,
                        'opening_account_balance' => $openingAccountBalance,
                        'opening_user_balance' => $openingUserBalance,
                        'souce_type' => 'CASH_ON_HAND',
                        'remarks' => "Expense triggered by Numeric Wastage stock transaction #{$stock->stock_id}",
                        'added_by' => $addedBy,
                        'stock_id' => $stock->stock_id,
                        'added_at' => $addedAt,
                        'created_at' => $addedAt,
                        'updated_at' => $addedAt,
                    ]);

                    $cashTxnId = $cashTxn->txn_id;

                    // Update cash balances
                    $givenBy->rak_cash_balance = $this->sub($givenBy->rak_cash_balance, $amount);
                    $givenBy->save();

                    $givenTo->rak_cash_balance = $this->add($givenTo->rak_cash_balance, $amount);
                    $givenTo->save();
                }

                // Create Numeric Wastages record
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
                    'added_at' => $addedAt,
                ]);

                $createdWastages[] = $nw;
            }

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdWastages;
        });
    }

    /**
     * 5. Hide specific stock details and their parent
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
     * 6. Cash / RTGS Transfer (Cash Out)
     */
    public function createCashOut(array $data, int $addedBy): CashTxn
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $givenBy = UserDetail::findOrFail($addedBy); // head/sender
            $givenTo = UserDetail::findOrFail($data['given_to']); // employee/receiver
            $amount = $data['amount'];
            $remarks = $data['remarks'] ?? null;

            $openingAccountBalance = $givenBy->rak_cash_balance;
            $openingUserBalance = $givenTo->rak_cash_balance;

            // Create cash transaction record
            $cashTxn = CashTxn::create([
                'type' => 'EXPENSE',
                'given_to' => $givenTo->user_id,
                'given_by' => $givenBy->user_id,
                'amount' => $amount,
                'opening_account_balance' => $openingAccountBalance,
                'opening_user_balance' => $openingUserBalance,
                'souce_type' => 'CASH_ON_HAND',
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
