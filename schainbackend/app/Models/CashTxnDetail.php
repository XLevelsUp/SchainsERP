<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTxnDetail extends Model
{
    protected $table = 'cash_txn_details';

    protected $primaryKey = 'txn_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [

        'type',
        'given_to',
        'given_by',
        'category_id',
        'amount',
        'balance',

        'opening_account_balance',
        'opening_user_balance',

        'opening_bank_account_balance',
        'opening_bank_user_balance',

        'closing_account_balance',
        'closing_user_balance',

        'closing_bank_account_balance',
        'closing_bank_user_balance',

        'souce_type',
        'bank_id',

        'remarks',
        'remainder',
        'remainder_at',

        'added_at',
        'added_by',

        'is_active',
        'is_hidden',
        'is_show_to_all',

        'amount_transfer_id',
        'image_url',

        'cash_to_gold_id',
        'stock_id',

        'amnt_transfer_from_head',
        'internal_type',

        'retailer_id',
        'retailer_ob_cash_balance',
        'retailer_ob_rtgs_balance',

        'txn_type',
        'bank_entry_date',

        'machine_vendor_id',
        'machine_vendor_ob_cash_balance',
        'machine_vendor_ob_rtgs_balance',

        'is_bill_cash',
        'is_payment_cash',
        'is_customer_affect',
        'is_need_receipt',

        'bill_payment_cash_type',

        'partial_amount',
        'actual_amount',

        'receipt_cash_txn_id',

        'given_by_arithmetic_operation',
        'given_to_arithmetic_operation',

        'cash_loan_type',

        'per_gram_cash',

        'over_all_bill_id',
        'estimate_retailer_bill_id',
        'estimate_metal_bill_id',

        'is_admin_head_entry',
        'admin_head_txn_id',
    ];

    protected $casts = [

        'amount' => 'double',
        'balance' => 'double',

        'opening_account_balance' => 'double',
        'opening_user_balance' => 'double',

        'opening_bank_account_balance' => 'double',
        'opening_bank_user_balance' => 'double',

        'closing_account_balance' => 'double',
        'closing_user_balance' => 'double',

        'closing_bank_account_balance' => 'double',
        'closing_bank_user_balance' => 'double',

        'retailer_ob_cash_balance' => 'double',
        'retailer_ob_rtgs_balance' => 'double',

        'machine_vendor_ob_cash_balance' => 'double',
        'machine_vendor_ob_rtgs_balance' => 'double',

        'partial_amount' => 'double',
        'actual_amount' => 'double',

        'per_gram_cash' => 'double',

        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'is_show_to_all' => 'boolean',

        'amnt_transfer_from_head' => 'boolean',

        'is_bill_cash' => 'boolean',
        'is_payment_cash' => 'boolean',
        'is_customer_affect' => 'boolean',
        'is_need_receipt' => 'boolean',

        'is_admin_head_entry' => 'boolean',

        'added_at' => 'datetime',
        'remainder_at' => 'datetime',
        'bank_entry_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASH TRANSACTION IMAGES
    |--------------------------------------------------------------------------
    */

    public function images(): HasMany
    {
        return $this->hasMany(
            CashTxnImage::class,
            'txn_id',
            'txn_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BANK
    |--------------------------------------------------------------------------
    */

    public function bank(): BelongsTo
    {
        return $this->belongsTo(
            BankDetail::class,
            'bank_id',
            'bank_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER - GIVEN BY
    |--------------------------------------------------------------------------
    */

    public function givenByUser(): BelongsTo
    {
        return $this->belongsTo(
            UserDetail::class,
            'given_by',
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER - GIVEN TO
    |--------------------------------------------------------------------------
    */

    public function givenToUser(): BelongsTo
    {
        return $this->belongsTo(
            UserDetail::class,
            'given_to',
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE FULL URL
    |--------------------------------------------------------------------------
    */

    public function getImageFullUrlAttribute()
    {
        if (!$this->image_url) {
            return null;
        }

        return asset(
            'storage/' . $this->image_url
        );
    }
}