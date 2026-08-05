<?php

namespace App\Observers;

use App\Models\StockInDetail;
use Illuminate\Support\Facades\Cache;

class StockInDetailObserver
{
    public function created(StockInDetail $stockInDetail): void
    {
        $this->invalidateCache($stockInDetail);
    }

    public function updated(StockInDetail $stockInDetail): void
    {
        $this->invalidateCache($stockInDetail);
    }

    public function deleted(StockInDetail $stockInDetail): void
    {
        $this->invalidateCache($stockInDetail);
    }

    public function restored(StockInDetail $stockInDetail): void
    {
        $this->invalidateCache($stockInDetail);
    }

    public function forceDeleted(StockInDetail $stockInDetail): void
    {
        $this->invalidateCache($stockInDetail);
    }

    protected function invalidateCache(StockInDetail $stockInDetail): void
    {
        $userIds = array_unique(array_filter([
            $stockInDetail->given_by,
            $stockInDetail->given_to,
            $stockInDetail->added_by,
        ]));

        foreach ($userIds as $userId) {
            Cache::forget("user:{$userId}:balances");
            Cache::forget("user:{$userId}:item_balances");
        }

        if ($stockInDetail->retailer_id) {
            Cache::forget("retailer:{$stockInDetail->retailer_id}:balances");
        }

        if ($stockInDetail->to_retailer_id) {
            Cache::forget("retailer:{$stockInDetail->to_retailer_id}:balances");
        }
    }
}
