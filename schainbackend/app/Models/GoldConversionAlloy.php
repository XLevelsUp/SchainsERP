<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldConversionAlloy extends Model
{
    protected $table = 'gold_conversion_alloys';

    // No default timestamps columns updated_at, only created_at is managed or set via timestamp
    public $timestamps = false;

    protected $fillable = [
        'conversion_id',
        'alloy_item_id',
        'alloy_percentage',
        'alloy_grams',
        'created_at',
    ];

    protected $casts = [
        'conversion_id' => 'integer',
        'alloy_item_id' => 'integer',
        'alloy_percentage' => 'decimal:4',
        'alloy_grams' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(GoldConversion::class, 'conversion_id', 'id');
    }

    public function alloyItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'alloy_item_id', 'item_id');
    }
}
