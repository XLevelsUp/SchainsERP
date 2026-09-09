<?php

namespace App\Services;

use App\Models\StockDetails;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FitemService
{
    /**
     * Process F-Items IN or OUT
     */
    public function processFitems(array $data, int $actingUserId): array
    {
        return DB::transaction(function () use ($data, $actingUserId) {
            $type = $data['type']; // 'IN' or 'OUT'
            $stockType = $type;
            
            $now = Carbon::now();
            $addedAt = !empty($data['added_at']) ? Carbon::parse($data['added_at']) : $now;
            
            $processedStocks = [];
            
            foreach ($data['items'] as $index => $itemData) {
                $grams = (float)$itemData['grams'];
                $touch = (float)$itemData['touch'];
                $purity = $grams * ($touch / 100);
                
                // 1. Insert into stock_details FIRST to get stock_id for FK
                $stockModel = new StockDetails();
                
                if ($type === 'IN') {
                    $stockModel->balance = $grams;
                } else {
                    $stockModel->balance = 0; // Out items start depleted
                }

                $stockModel->given_by = $data['given_by'];
                $stockModel->given_to = $data['given_to'];
                $stockModel->added_by = $actingUserId;
                $stockModel->entry_type = 'NORMAL'; 
                $stockModel->type = 'FITEM';
                $stockModel->stock_type = $stockType;
                $stockModel->item_id = $itemData['item_id'];
                $stockModel->grams = $grams;
                $stockModel->touch = $touch;
                $stockModel->mtouch = $itemData['mtouch'] ?? 0;
                $stockModel->purity = $purity;
                $stockModel->waste_value = $itemData['wastage'] ?? 0;
                $stockModel->no_of_pcs = $itemData['item_no_of_pcs'] ?? null;
                $stockModel->item_remarks = $itemData['item_remarks'] ?? null;
                $stockModel->remarks = $itemData['remarks'] ?? null;
                $stockModel->added_at = $addedAt;

                $stockModel->save();

                // 2. Insert into fitem_histories
                $fitemId = DB::table('fitem_histories')->insertGetId([
                    'grams' => $grams,
                    'touch' => $touch,
                    'purity' => $purity,
                    'mtouch' => $itemData['mtouch'] ?? 0,
                    'wastage' => $itemData['wastage'] ?? 0,
                    'total' => $purity,
                    'fitem_type' => $type,
                    'fitem_stock_out_id' => $stockModel->stock_id,
                    'box_id' => $itemData['box_id'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $processedStocks[] = $stockModel;
            }

            return $processedStocks;
        });
    }


}
