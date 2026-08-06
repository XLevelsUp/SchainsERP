<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GmsInHistory extends Model
{
    use HasFactory;

    protected $table = 'gms_in_histories';
    protected $primaryKey = 'gms_id';

    protected $fillable = [
        'item_id',
        'grams',
        'stone',
        'thread',
        'mtouch',
        'mtouch_wastage',
        'wastage',
        'hall_mark',
        'total',
        'gms_type',
        'gms_stock_in_id',
        'added_at',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'grams' => 'decimal:4',
        'stone' => 'decimal:4',
        'thread' => 'decimal:4',
        'mtouch' => 'decimal:4',
        'mtouch_wastage' => 'decimal:4',
        'wastage' => 'decimal:4',
        'hall_mark' => 'decimal:4',
        'total' => 'decimal:4',
        'gms_stock_in_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockInDetail::class, 'gms_stock_in_id', 'stock_in_detail_id');
    }
}
