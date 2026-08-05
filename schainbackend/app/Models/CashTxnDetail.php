<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTxnDetail extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'cash_txn_details';


    /*
    |--------------------------------------------------------------------------
    | Primary Key
    |--------------------------------------------------------------------------
    */

    protected $primaryKey = 'txn_id';

    public $incrementing = true;

    protected $keyType = 'int';


    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    |
    | Your table uses added_at instead of Laravel's
    | created_at and updated_at.
    |
    */

    public $timestamps = false;


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        /*
        |--------------------------------------------------------------------------
        | Basic Transaction
        |--------------------------------------------------------------------------
        */

        'type',
        'given_to',
        'given_by',
        'category_id',
        'amount',
        'balance',


        /*
        |--------------------------------------------------------------------------
        | Opening Balances
        |--------------------------------------------------------------------------
        */

        'opening_account_balance',
        'opening_user_balance',
        'opening_bank_account_balance',
        'opening_bank_user_balance',


        /*
        |--------------------------------------------------------------------------
        | Closing Balances
        |--------------------------------------------------------------------------
        */

        'closing_account_balance',
        'closing_user_balance',
        'closing_bank_account_balance',
        'closing_bank_user_balance',


        /*
        |--------------------------------------------------------------------------
        | Source / Bank
        |--------------------------------------------------------------------------
        */

        'souce_type',
        'bank_id',


        /*
        |--------------------------------------------------------------------------
        | Remarks
        |--------------------------------------------------------------------------
        */

        'remarks',
        'remainder',
        'remainder_at',


        /*
        |--------------------------------------------------------------------------
        | Added Information
        |--------------------------------------------------------------------------
        */

        'added_at',
        'added_by',


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'is_active',
        'is_hidden',
        'is_show_to_all',


        /*
        |--------------------------------------------------------------------------
        | Transaction References
        |--------------------------------------------------------------------------
        */

        'amount_transfer_id',
        'image_url',
        'cash_to_gold_id',
        'stock_id',


        /*
        |--------------------------------------------------------------------------
        | Head / Internal
        |--------------------------------------------------------------------------
        */

        'amnt_transfer_from_head',
        'internal_type',


        /*
        |--------------------------------------------------------------------------
        | Retailer
        |--------------------------------------------------------------------------
        */

        'retailer_id',
        'retailer_ob_cash_balance',
        'retailer_ob_rtgs_balance',


        /*
        |--------------------------------------------------------------------------
        | Transaction Type
        |--------------------------------------------------------------------------
        */

        'txn_type',
        'bank_entry_date',


        /*
        |--------------------------------------------------------------------------
        | Machine Vendor
        |--------------------------------------------------------------------------
        */

        'machine_vendor_id',
        'machine_vendor_ob_cash_balance',
        'machine_vendor_ob_rtgs_balance',


        /*
        |--------------------------------------------------------------------------
        | Bill / Payment
        |--------------------------------------------------------------------------
        */

        'is_bill_cash',
        'is_payment_cash',
        'is_customer_affect',
        'is_need_receipt',

        'bill_payment_cash_type',


        /*
        |--------------------------------------------------------------------------
        | Amount Details
        |--------------------------------------------------------------------------
        */

        'partial_amount',
        'actual_amount',
        'receipt_cash_txn_id',


        /*
        |--------------------------------------------------------------------------
        | Arithmetic Operation
        |--------------------------------------------------------------------------
        */

        'given_by_arithmetic_operation',
        'given_to_arithmetic_operation',


        /*
        |--------------------------------------------------------------------------
        | Cash Loan
        |--------------------------------------------------------------------------
        */

        'cash_loan_type',


        /*
        |--------------------------------------------------------------------------
        | Per Gram
        |--------------------------------------------------------------------------
        */

        'per_gram_cash',


        /*
        |--------------------------------------------------------------------------
        | Bill References
        |--------------------------------------------------------------------------
        */

        'over_all_bill_id',
        'estimate_retailer_bill_id',
        'estimate_metal_bill_id',


        /*
        |--------------------------------------------------------------------------
        | Admin Head
        |--------------------------------------------------------------------------
        */

        'is_admin_head_entry',
        'admin_head_txn_id',
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        /*
        |--------------------------------------------------------------------------
        | Amounts
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | Boolean
        |--------------------------------------------------------------------------
        */

        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
        'is_show_to_all' => 'boolean',

        'amnt_transfer_from_head' => 'boolean',

        'is_bill_cash' => 'boolean',
        'is_payment_cash' => 'boolean',
        'is_customer_affect' => 'boolean',
        'is_need_receipt' => 'boolean',

        'is_admin_head_entry' => 'boolean',


        /*
        |--------------------------------------------------------------------------
        | Date / Time
        |--------------------------------------------------------------------------
        */

        'added_at' => 'datetime',
        'remainder_at' => 'datetime',
        'bank_entry_date' => 'date',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationship: Images
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
    | Relationship: Bank
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
    | Relationship: Cash To Gold
    |--------------------------------------------------------------------------
    |
    | cash_txn_details.cash_to_gold_id
    |              ↓
    | cash_to_gold.cash_to_gold_id
    |
    */

    public function cashToGold(): BelongsTo
    {
        return $this->belongsTo(
            CashToGold::class,
            'cash_to_gold_id',
            'cash_to_gold_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Relationship: Given By User
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
    | Relationship: Given To User
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
    | Accessor: Image Full URL
    |--------------------------------------------------------------------------
    */

    public function getImageFullUrlAttribute(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        return asset(
            'storage/' . $this->image_url
        );
    }
}