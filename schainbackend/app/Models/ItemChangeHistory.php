<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemChangeHistory extends Model
{
    protected $table = 'item_change_history';
    protected $primaryKey = 'change_id';

    protected $fillable = [
        'from_item_id',
        'to_item_id',
        'grams',
        'from_touch',
        'req_touch',
        'total',
        'change_type',
        'out_stock_id',
        'in_stock_id',
        'added_at',
    ];

    protected $casts = [
        'from_item_id' => 'integer',
        'to_item_id' => 'integer',
        'grams' => 'decimal:4',
        'from_touch' => 'decimal:4',
        'req_touch' => 'decimal:4',
        'total' => 'decimal:4',
        'out_stock_id' => 'integer',
        'in_stock_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function fromItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'from_item_id', 'item_id');
    }

    public function toItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'to_item_id', 'item_id');
    }

    public function outStock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'out_stock_id', 'stock_id');
    }

    public function inStock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'in_stock_id', 'stock_id');
    }
}
