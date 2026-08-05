<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OverAllBill extends Model
{
    protected $table = 'over_all_bills';

    protected $fillable = [
        'is_active',
        'mc',
        'is_cash_updated',
        'cash_txn_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_cash_updated' => 'boolean',
        'mc' => 'double',
        'cash_txn_id' => 'integer',
    ];

    public function billingEntries(): HasMany
    {
        return $this->hasMany(BillingEntry::class, 'over_all_bill_id', 'id');
    }
}
