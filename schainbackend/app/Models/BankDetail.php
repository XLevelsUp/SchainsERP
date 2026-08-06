<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankDetail extends Model
{
    use HasFactory;

    protected $table = 'bank_details';

    protected $primaryKey = 'bank_id';

    protected $fillable = [
        'account_name',
        'ledger_balance',
        'is_active',
    ];

    protected $casts = [
        'bank_id' => 'integer',
        'ledger_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTxnDetail::class, 'bank_account_id', 'bank_id');
    }
}