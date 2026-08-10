<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class PurchaseGold extends CashToGold
{
    /**
     * The booted method of the model.
     */
    protected static function booted()
    {
        static::addGlobalScope('purchase_gold', function (Builder $builder) {
            $builder->whereIn('type', ['HEAD', 'OUT_CASH_CONVERTER']);
        });
    }
}
