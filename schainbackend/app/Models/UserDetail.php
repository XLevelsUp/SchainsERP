<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $table = 'user_details';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    const CREATED_AT = 'added_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'user_name',
        'password_hash',
        'address',
        'signature',
        'code',
        'phone_no',
        'remarks',
        'proff',
        'role',
        'customer_commants',
        'mailing_name',
        'image_url',
        'profile_image',
        'category_name',
        'system_id',
        'is_active',
        'is_delete',
        'is_billable',
        'is_create_order_shown',
        'grams_grand_total',
        'purity_grand_total',
        'stone_weight_grand_total',
        'beads_weight_grand_total',
        'net_weight_grand_total',
        'gross_weight_grand_total',
        'order_grand_total',
        'order_grand_no_of_pcs',
        'is_salary_person',
        'per_day_salary',
        'rak_cash_balance',
        'last_cash_txn_date',
        'is_gold_cal_enabled',
        'is_cash_cal_enabled',
        'is_wastage_cal_enabled',
        'report_password',
        'otp',
        'is_otp_verified',
        'allot_value',
        'rak_rtgs_balance',
        'vendor_short_name',
        'credit_out_limit',
        'credit_in_limit',
        'credit_limit_remarks',
        'credit_limit_updated_at',
        'credit_limit_updated_by',
        'temp_gram_credit_limit',
        'temp_purity_credit_limit',
        'default_mc_choice',
        'page_access',
        'is_admin_head',
        'main_head_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_delete' => 'boolean',
        'is_billable' => 'boolean',
        'is_otp_verified' => 'boolean',
        'is_salary_person' => 'boolean',
        'is_gold_cal_enabled' => 'boolean',
        'is_cash_cal_enabled' => 'boolean',
        'is_wastage_cal_enabled' => 'boolean',
        'added_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_txn_date' => 'datetime',
        'last_rtgs_txn_date' => 'datetime',
        'last_cash_txn_date' => 'date',
    ];
}