<?php

namespace App\Services;

use App\Models\UserDetail;
use App\Models\StockDetails;
use App\Models\OverAllBill;
use App\Models\BillingEntry;
use App\Models\GmsHistory;
use App\Models\FitemHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AutoEntryService extends BaseStockService
{
    /**
     * Process Auto Entry transaction (unified OUT transfer between users)
     */
    public function executeAutoTransfer(array $data, int $addedBy): array
    {
        return DB::transaction(function () use ($data, $addedBy) {
            $type = $data['type'];
            $addedAt = isset($data['added_at']) ? \Illuminate\Support\Carbon::parse($data['added_at']) : now();

            // 1. Resolve sender, receiver, and item IDs based on transaction type
            $givenByUserId = null;
            $givenToUserId = null;
            $fromItemId = null;
            $toItemId = null;
            $retailerId = null;
            $toRetailerId = null;

            if ($type === 'EMPTOEMP') {
                $givenByUserId = $data['from_employee'];
                $givenToUserId = $data['to_employee'];
                $fromItemId = $data['emp_item_id1'];
                $toItemId = $data['emp_item_id2'];
                $retailerId = $data['from_retailer'] ?? null;
                $toRetailerId = $data['to_retailer'] ?? null;
            } elseif ($type === 'EMPTOHEAD') {
                $givenByUserId = $data['from_employee1'];
                $givenToUserId = $data['to_head'];
                $fromItemId = $data['emp_item_id3'];
                $toItemId = $data['head_item_id'];
                $retailerId = $data['from_retailer1'] ?? null;
            } elseif ($type === 'ANOTHERHEADTOEMP') {
                $givenByUserId = $data['from_head'];
                $givenToUserId = $data['to_employee1'];
                $fromItemId = $data['head_item_id1'];
                $toItemId = $data['emp_item_id4'];
                $toRetailerId = $data['to_retailer1'] ?? null;
            } elseif ($type === 'HEADTOHEAD') {
                $givenByUserId = $data['from_head1'];
                $givenToUserId = $data['to_head1'];
                $fromItemId = $data['head_item_id2'];
                $toItemId = $data['head_item_id3'];
            }

            // 2. Lock users for updates
            $givenBy = UserDetail::where('user_id', $givenByUserId)->lockForUpdate()->firstOrFail();
            $givenTo = UserDetail::where('user_id', $givenToUserId)->lockForUpdate()->firstOrFail();

            // 3. Create Billing entries
            $overallBill = OverAllBill::create([
                'is_active' => true,
                'is_cash_updated' => false,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            $billingEntry = BillingEntry::create([
                'over_all_bill_id' => $overallBill->id,
                'type' => 'OUT',
                'head_id' => $givenBy->user_id,
                'user_id' => $givenTo->user_id,
                'ob_purity' => $givenTo->purity_grand_total,
                'ob_grams' => $givenTo->grams_grand_total,
                'from_ob_purity' => $givenBy->purity_grand_total,
                'from_ob_grams' => $givenBy->grams_grand_total,
                'added_at' => $addedAt,
                'created_at' => $addedAt,
                'updated_at' => $addedAt,
            ]);

            $createdStocks = [];

            // 4. Process each row item
            foreach ($data['items'] as $itemData) {
                $grams = $itemData['grams'];
                $touch = $itemData['touch'];
                $toTouch = $itemData['to_touch'];
                $rowType = $itemData['type'];
                $remarks = $itemData['remarks'] ?? null;
                $itemRemarks = $itemData['item_remarks'] ?? null;
                $wasteId = $itemData['waste_id'] ?? null;
                $wasteTotal = $itemData['waste_total'] ?? 0;
                $toWasteId = $itemData['to_waste_id'] ?? null;
                $toWasteTotal = $itemData['to_waste_total'] ?? 0;

                // Goldsmith Outward parameters
                $stone = $itemData['stone'] ?? 0;
                $thread = $itemData['thread'] ?? 0;
                $toStone = $itemData['to_stone'] ?? 0;
                $toThread = $itemData['to_thread'] ?? 0;
                $gmsMtouch = $itemData['gms_mtouch'] ?? 0;
                $gmsMtouchWastage = $itemData['gms_mthouch_wastage'] ?? 0;
                $toGmsMtouch = $itemData['to_gms_mtouch'] ?? 0;
                $toGmsMtouchWastage = $itemData['to_gms_mthouch_wastage'] ?? 0;

                // Finished Item parameters
                $boxId = $itemData['box_id'] ?? null;
                $mtouch = $itemData['mtouch'] ?? 0;
                $toMtouch = $itemData['to_mtouch'] ?? 0;

                // Determine sender waste value & purity
                if ($rowType === 'GMS') {
                    $netGrams = $this->sub($grams, $this->add($stone, $thread));
                    $wasteValue = $itemData['waste_value'] ?? $this->div($this->mul($netGrams, $wasteTotal), '100');
                    $purity = $itemData['purity'] ?? $this->div($this->mul($this->add($netGrams, $wasteValue), $touch), '100');

                    $toNetGrams = $this->sub($grams, $this->add($toStone, $toThread));
                    $toWasteValue = $itemData['to_waste_value'] ?? $this->div($this->mul($toNetGrams, $toWasteTotal), '100');
                    $toPurity = $itemData['to_purity'] ?? $this->div($this->mul($this->add($toNetGrams, $toWasteValue), $toTouch), '100');
                } else {
                    $wasteValue = $itemData['waste_value'] ?? $this->div($this->mul($grams, $wasteTotal), '100');
                    $purity = $itemData['purity'] ?? $this->add($this->div($this->mul($grams, $touch), '100'), $wasteValue);

                    $toWasteValue = $itemData['to_waste_value'] ?? $this->div($this->mul($grams, $toWasteTotal), '100');
                    $toPurity = $itemData['to_purity'] ?? $this->add($this->div($this->mul($grams, $toTouch), '100'), $toWasteValue);
                }

                // Determine role-based weight adjustments
                $isSenderHead = ($givenBy->role_id == 1);
                $isReceiverHead = ($givenTo->role_id == 1);

                $givenbygrams = $isSenderHead ? $grams : $this->add($grams, $wasteValue);
                $giventograms = $isReceiverHead ? $grams : $this->add($grams, $toWasteValue);

                // Build OB snapshot
                $obSnapshot = [
                    'given_by_details' => [
                        'ob' => $this->buildUserItemDetails($givenBy->user_id, $fromItemId, $toItemId, 'ob'),
                    ],
                    'given_to_details' => [
                        'ob' => $this->buildUserItemDetails($givenTo->user_id, $fromItemId, $toItemId, 'ob'),
                    ]
                ];

                // Create Stock Detail OUT row
                $stock = StockDetails::create([
                    'item_id' => $fromItemId,
                    'given_by' => $givenBy->user_id,
                    'given_to' => $givenTo->user_id,
                    'type' => $rowType,
                    'entry_type' => $type,
                    'stock_type' => 'OUT',
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'to_item_id' => $toItemId,
                    'to_touch' => $toTouch,
                    'to_purity' => $toPurity,
                    'remarks' => $remarks ?? "Auto Entry transfer",
                    'item_remarks' => $itemRemarks,
                    'waste_id' => $wasteId,
                    'waste_total' => $wasteTotal,
                    'waste_value' => $wasteValue,
                    'to_waste_id' => $toWasteId,
                    'to_waste_total' => $toWasteTotal,
                    'to_waste_value' => $toWasteValue,
                    'mtouch' => $mtouch,
                    'gms_mtouch' => $gmsMtouch,
                    'gms_mthouch_wastage' => $gmsMtouchWastage,
                    'bill_id' => $billingEntry->bill_id,
                    'balance' => $grams,
                    'added_by' => $addedBy,
                    'retailer_id' => $retailerId,
                    'to_retailer_id' => $toRetailerId,
                    'given_by_item_grams_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_to_item_grams_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_grams'] ?? 0,
                    'given_by_item_purity_op' => $obSnapshot['given_by_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'given_to_item_purity_op' => $obSnapshot['given_to_details']['ob']['item_details']['ob_purity'] ?? 0,
                    'added_at' => $addedAt,
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                // Create auxiliary history records
                if ($rowType === 'GMS') {
                    GmsHistory::create([
                        'item_id' => $fromItemId,
                        'grams' => $grams,
                        'stone' => $stone,
                        'thread' => $thread,
                        'wastage' => $wasteTotal,
                        'hall_mark' => $touch,
                        'total' => $purity,
                        'mtouch' => $gmsMtouch,
                        'mtouch_wastage' => $gmsMtouchWastage,
                        'to_mtouch' => $toGmsMtouch,
                        'to_mtouch_wastage' => $toGmsMtouchWastage,
                        'to_stone' => $toStone,
                        'to_thread' => $toThread,
                        'to_wastage' => $toWasteTotal,
                        'to_hall_mark' => $toTouch,
                        'to_total' => $toPurity,
                        'to_item_id' => $toItemId,
                        'gms_type' => 'OUT',
                        'gms_stock_out_id' => $stock->stock_id,
                    ]);
                } elseif ($rowType === 'FITEM') {
                    FitemHistory::create([
                        'grams' => $grams,
                        'touch' => $touch,
                        'purity' => $purity,
                        'mtouch' => $mtouch,
                        'wastage' => $wasteValue,
                        'total' => $purity,
                        'fitem_type' => 'OUT',
                        'fitem_stock_out_id' => $stock->stock_id,
                        'box_id' => $boxId,
                    ]);
                }

                // Balance Updates
                // 1. Deduct from givenBy
                $givenBy->grams_grand_total = $this->sub($givenBy->grams_grand_total, $givenbygrams);
                $givenBy->purity_grand_total = $this->sub($givenBy->purity_grand_total, $purity);
                $givenBy->save();

                $this->updateUserItemBalance($givenBy->user_id, $fromItemId, "-$givenbygrams", "-$purity");

                // 2. Add to givenTo
                $givenTo->grams_grand_total = $this->add($givenTo->grams_grand_total, $giventograms);
                $givenTo->purity_grand_total = $this->add($givenTo->purity_grand_total, $toPurity);
                $givenTo->save();

                $this->updateUserItemBalance($givenTo->user_id, $toItemId, $giventograms, $toPurity);

                // Build CB snapshot
                $cbSnapshot = [
                    'given_by_details' => [
                        'cb' => $this->buildUserItemDetails($givenBy->user_id, $fromItemId, $toItemId, 'cb'),
                    ],
                    'given_to_details' => [
                        'cb' => $this->buildUserItemDetails($givenTo->user_id, $fromItemId, $toItemId, 'cb'),
                    ]
                ];

                // Merge and save snapshot
                $snapshot = array_merge_recursive($obSnapshot, $cbSnapshot);
                $stock->obcb_details = $snapshot;
                $stock->given_by_item_grams_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_grams'] ?? 0;
                $stock->given_to_item_grams_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_grams'] ?? 0;
                $stock->given_by_item_purity_cb = $cbSnapshot['given_by_details']['cb']['item_details']['cb_purity'] ?? 0;
                $stock->given_to_item_purity_cb = $cbSnapshot['given_to_details']['cb']['to_item_details']['cb_purity'] ?? 0;
                $stock->saveQuietly();

                $createdStocks[] = $stock;
            }

            // 5. Update Billing Closing snapshot
            $billingEntry->update([
                'cb_purity' => $givenTo->purity_grand_total,
                'cb_grams' => $givenTo->grams_grand_total,
                'from_cb_purity' => $givenBy->purity_grand_total,
                'from_cb_grams' => $givenBy->grams_grand_total,
            ]);

            Cache::forget("user:{$givenBy->user_id}:balances");
            Cache::forget("user:{$givenTo->user_id}:balances");

            return $createdStocks;
        });
    }
}
