<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GmsHistory extends Model
{
    protected $table = 'gms_history';
    protected $primaryKey = 'gms_id';

    protected $fillable = [
        'item_id',
        'grams',
        'stone',
        'thread',
        'wastage',
        'hall_mark',
        'total',
        'mtouch',
        'mtouch_wastage',
        'to_mtouch',
        'to_mtouch_wastage',
        'to_stone',
        'to_thread',
        'to_wastage',
        'to_hall_mark',
        'to_total',
        'to_item_id',
        'gms_type',
        'gms_stock_in_id',
        'gms_stock_out_id',
        'added_at',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'grams' => 'decimal:4',
        'stone' => 'decimal:4',
        'thread' => 'decimal:4',
        'wastage' => 'decimal:4',
        'hall_mark' => 'decimal:4',
        'total' => 'decimal:4',
        'mtouch' => 'decimal:4',
        'mtouch_wastage' => 'decimal:4',
        'to_mtouch' => 'decimal:4',
        'to_mtouch_wastage' => 'decimal:4',
        'to_stone' => 'decimal:4',
        'to_thread' => 'decimal:4',
        'to_wastage' => 'decimal:4',
        'to_hall_mark' => 'decimal:4',
        'to_total' => 'decimal:4',
        'to_item_id' => 'integer',
        'gms_stock_in_id' => 'integer',
        'gms_stock_out_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function toItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'to_item_id', 'item_id');
    }

    public function gmsStockIn(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'gms_stock_in_id', 'stock_id');
    }

    public function gmsStockOut(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'gms_stock_out_id', 'stock_id');
    }
}
