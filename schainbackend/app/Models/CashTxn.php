<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTxn extends Model
{
    protected $table = 'cash_txn_details';
    protected $primaryKey = 'txn_id';
    public $timestamps = false;

    protected $fillable = [
        'type',
        'given_to',
        'given_by',
        'category_id',
        'amount',
        'opening_account_balance',
        'opening_user_balance',
        'souce_type',
        'bank_id',
        'remarks',
        'added_by',
        'is_active',
        'is_hidden',
        'is_show_to_all',
        'amount_transfer_id',
        'image_url',
        'stock_id',
        'retailer_id',
        'retailer_ob_cash_balance',
        'added_at',
    ];

    protected $casts = [
        'given_to' => 'integer',
        'given_by' => 'integer',
        'category_id' => 'integer',
        'amount' => 'decimal:4',
        'opening_account_balance' => 'decimal:4',
        'opening_user_balance' => 'decimal:4',
        'bank_id' => 'integer',
        'added_by' => 'integer',
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'is_show_to_all' => 'boolean',
        'amount_transfer_id' => 'integer',
        'stock_id' => 'integer',
        'retailer_id' => 'integer',
        'retailer_ob_cash_balance' => 'decimal:4',
        'added_at' => 'datetime',
    ];

    public function givenTo(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'given_to', 'user_id');
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'given_by', 'user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'added_by', 'user_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'stock_id', 'stock_id');
    }
}
