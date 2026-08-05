<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldConversion extends Model
{
    protected $table = 'gold_conversions';

    protected $fillable = [
        'source_item_id',
        'target_item_id',
        'source_grams',
        'source_touch',
        'target_touch',
        'converted_grams',
        'in_stock_id',
        'out_stock_id',
        'billing_entry_id',
        'added_at',
    ];

    protected $casts = [
        'source_item_id' => 'integer',
        'target_item_id' => 'integer',
        'source_grams' => 'decimal:4',
        'source_touch' => 'decimal:4',
        'target_touch' => 'decimal:4',
        'converted_grams' => 'decimal:4',
        'in_stock_id' => 'integer',
        'out_stock_id' => 'integer',
        'billing_entry_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'source_item_id', 'item_id');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id', 'item_id');
    }

    public function outStock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'out_stock_id', 'stock_id');
    }

    public function inStock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'in_stock_id', 'stock_id');
    }

    public function billingEntry(): BelongsTo
    {
        return $this->belongsTo(BillingEntry::class, 'billing_entry_id', 'bill_id');
    }

    public function alloys(): HasMany
    {
        return $this->hasMany(GoldConversionAlloy::class, 'conversion_id', 'id');
    }
}
