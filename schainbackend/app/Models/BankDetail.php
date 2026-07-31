<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankDetail extends Model
{
    protected $table = 'bank_details';

    protected $primaryKey = 'bank_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'bank_name',
        'current_balance',
        'is_active',
        'added_at',
    ];

    protected $casts = [
        'bank_id' => 'integer',
        'current_balance' => 'double',
        'is_active' => 'boolean',
        'added_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Bank Transactions
    |--------------------------------------------------------------------------
    */

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(
            CashTxnDetail::class,
            'bank_id',
            'bank_id'
        );
    }
}