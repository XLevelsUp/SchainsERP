<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FitemHistory extends Model
{
    protected $table = 'fitem_histories';

    protected $fillable = [
        'grams',
        'touch',
        'purity',
        'mtouch',
        'wastage',
        'total',
        'fitem_type',
        'fitem_stock_out_id',
        'box_id',
    ];

    protected $casts = [
        'grams' => 'decimal:4',
        'touch' => 'decimal:4',
        'purity' => 'decimal:4',
        'mtouch' => 'decimal:4',
        'wastage' => 'decimal:4',
        'total' => 'decimal:4',
        'fitem_stock_out_id' => 'integer',
        'box_id' => 'integer',
    ];

    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'fitem_stock_out_id', 'stock_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(FitemBox::class, 'box_id', 'box_id');
    }
}
