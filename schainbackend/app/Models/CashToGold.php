<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashToGold extends Model
{
    protected $table = 'cash_to_gold';

    protected $primaryKey = 'cash_to_gold_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'head_id',
        'customer_id',
        'total_cash',
        'per_gram_cash',
        'total_grams',
        'touch',
        'purity',
        'item_id',
        'stock_id',
        'added_at',
        'added_by',
        'amnt_transfer_to_head',
        'taken_total_cash',
        'taken_total_grams',
        'taken_purity',
        'ob_grams',
        'ob_purity',
        'remarks',
        'retailer_id',
        'is_rate_avg',
        'partial_amount',
        'balance_amount',
        'adjust_cash',
    ];

    protected $casts = [
        'total_cash'            => 'double',
        'per_gram_cash'         => 'double',
        'total_grams'           => 'double',
        'touch'                 => 'double',
        'purity'                => 'double',
        'taken_total_cash'      => 'double',
        'taken_total_grams'     => 'double',
        'taken_purity'          => 'double',
        'ob_grams'              => 'double',
        'ob_purity'             => 'double',
        'partial_amount'        => 'double',
        'balance_amount'        => 'double',
        'adjust_cash'           => 'double',
        'amnt_transfer_to_head' => 'boolean',
        'is_rate_avg'           => 'boolean',
        'added_at'              => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function head(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'head_id', 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'customer_id', 'user_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'stock_id', 'stock_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'added_by', 'user_id');
    }

    public function amountSources(): HasMany
    {
        return $this->hasMany(
            CashToGoldAmountSource::class,
            'cash_to_gold_id',
            'cash_to_gold_id'
        );
    }

    public function cashTxnDetails(): HasMany
    {
        return $this->hasMany(
            CashTxnDetail::class,
            'cash_to_gold_id',
            'cash_to_gold_id'
        );
    }
}