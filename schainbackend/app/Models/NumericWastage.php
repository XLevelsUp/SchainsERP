<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumericWastage extends Model
{
    protected $table = 'numeric_wastages';

    protected $fillable = [
        'item_id',
        'grams',
        'touch',
        'no_of_pcs',
        'wastage_id',
        'wastage_value',
        'wastage_total',
        'type',
        'stock_id',
        'amount',
        'cash_txn_id',
        'added_at',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'grams' => 'decimal:4',
        'touch' => 'decimal:4',
        'no_of_pcs' => 'decimal:4',
        'wastage_id' => 'integer',
        'wastage_value' => 'decimal:4',
        'wastage_total' => 'decimal:4',
        'stock_id' => 'integer',
        'amount' => 'decimal:4',
        'cash_txn_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'stock_id', 'stock_id');
    }

    public function cashTxn(): BelongsTo
    {
        return $this->belongsTo(MonetaryTransaction::class, 'cash_txn_id', 'id');
    }
}
