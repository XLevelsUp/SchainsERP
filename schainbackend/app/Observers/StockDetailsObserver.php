<?php

namespace App\Observers;

use App\Models\StockDetails;
use Illuminate\Support\Facades\Cache;

class StockDetailsObserver
{
    /**
     * Handle the StockDetails "created" event.
     */
    public function created(StockDetails $stockDetails): void
    {
        $this->invalidateCache($stockDetails);
    }

    /**
     * Handle the StockDetails "updated" event.
     */
    public function updated(StockDetails $stockDetails): void
    {
        $this->invalidateCache($stockDetails);
    }

    /**
     * Handle the StockDetails "deleted" event.
     */
    public function deleted(StockDetails $stockDetails): void
    {
        $this->invalidateCache($stockDetails);
    }

    /**
     * Handle the StockDetails "restored" event.
     */
    public function restored(StockDetails $stockDetails): void
    {
        $this->invalidateCache($stockDetails);
    }

    /**
     * Handle the StockDetails "force deleted" event.
     */
    public function forceDeleted(StockDetails $stockDetails): void
    {
        $this->invalidateCache($stockDetails);
    }

    /**
     * Invalidate the Redis cache for affected users.
     */
    protected function invalidateCache(StockDetails $stockDetails): void
    {
        $userIds = array_unique(array_filter([
            $stockDetails->given_by,
            $stockDetails->given_to,
            $stockDetails->added_by,
        ]));

        foreach ($userIds as $userId) {
            Cache::forget("user:{$userId}:balances");
            Cache::forget("user:{$userId}:item_balances");
        }

        if ($stockDetails->retailer_id) {
            Cache::forget("retailer:{$stockDetails->retailer_id}:balances");
        }

        if ($stockDetails->to_retailer_id) {
            Cache::forget("retailer:{$stockDetails->to_retailer_id}:balances");
        }
    }
}
