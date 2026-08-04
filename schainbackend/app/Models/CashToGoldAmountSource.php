<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashToGoldAmountSource extends Model
{
    protected $table = 'cash_to_gold_amount_sources';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cash_to_gold_id',
        'cash_txn_id',
        'souce_type',
        'bank_id',
        'amount',
        'added_at',
    ];

    protected $casts = [
        'cash_to_gold_id' => 'integer',
        'cash_txn_id' => 'integer',
        'bank_id' => 'integer',
        'amount' => 'double',
        'added_at' => 'datetime',
    ];

    public function cashToGold(): BelongsTo
    {
        return $this->belongsTo(
            CashToGold::class,
            'cash_to_gold_id',
            'cash_to_gold_id'
        );
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(
            CashTxnDetail::class,
            'cash_txn_id',
            'txn_id'
        );
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(
            BankDetail::class,
            'bank_id',
            'bank_id'
        );
    }
}