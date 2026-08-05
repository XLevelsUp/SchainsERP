<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingEntry extends Model
{
    protected $table = 'billing_entries';
    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'over_all_bill_id',
        'type',
        'head_id',
        'user_id',
        'ob_purity',
        'ob_grams',
        'cb_purity',
        'cb_grams',
        'from_ob_purity',
        'from_ob_grams',
        'from_cb_purity',
        'from_cb_grams',
        'remarks',
        'added_at',
    ];

    protected $casts = [
        'over_all_bill_id' => 'integer',
        'head_id' => 'integer',
        'user_id' => 'integer',
        'ob_purity' => 'decimal:4',
        'ob_grams' => 'decimal:4',
        'cb_purity' => 'decimal:4',
        'cb_grams' => 'decimal:4',
        'from_ob_purity' => 'decimal:4',
        'from_ob_grams' => 'decimal:4',
        'from_cb_purity' => 'decimal:4',
        'from_cb_grams' => 'decimal:4',
        'added_at' => 'datetime',
    ];

    public function overAllBill(): BelongsTo
    {
        return $this->belongsTo(OverAllBill::class, 'over_all_bill_id', 'id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'head_id', 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'user_id', 'user_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(StockDetails::class, 'bill_id', 'bill_id');
    }
}
