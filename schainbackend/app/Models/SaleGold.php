<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SaleGold extends CashToGold
{
    /**
     * The booted method of the model.
     */
    protected static function booted()
    {
        static::addGlobalScope('sale_gold', function (Builder $builder) {
            $builder->whereIn('type', ['SALE_GOLD', 'SALE_GOLD_CASH', 'IN_CASH_CONVERTER']);
        });
    }
}
