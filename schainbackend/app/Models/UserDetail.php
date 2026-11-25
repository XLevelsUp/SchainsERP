<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDetail extends Model
{
    use HasFactory;

    protected $table = 'user_details';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [

        // User details
        'name',
        'user_name',
        'password_hash',
        'address',
        'signature',
        'code',
        'phone_no',
        'remarks',
        'proff',
        'role_id',
        'customer_commants',
        'mailing_name',
        'category_name',
        'system_id',

        // Images
        'profile_image',
        'aadhar_image',

        // Status
        'is_active',
        'is_delete',
        'is_billable',

        // User settings
        'is_customerfitem_cal_enabled',
        'is_customerfitem_cal_in_enabled',
        'is_create_order_shown',
        'is_salary_person',
        'is_gold_cal_enabled',
        'is_cash_cal_enabled',
        'is_wastage_cal_enabled',
        'is_otp_verified',

        'is_remainder_shown',
        'is_delivery_item_shown',

        'is_polish_needed',
        'is_wa_delivery_stock_needed',
        'is_polish_chk_need_shown',
        'is_delivery_chk_need_shown',
        'is_cashamt_thermal_shown',
        'is_customer_touch_need_shown',
        'is_complete_history_need_shown',
        'is_create_order_need_to_shown',
        'is_cash_mngmt_need_to_shown',
        'is_freeze_entry_need_to_shown',
        'is_admin_login_otp_need_to_shown',
        'is_act_as_head_enabled',
        'is_customer_cmts_need_to_shown',
        'is_outside_need_to_shown',
        'is_tally_need_to_shown',
        'is_die_num_search_need_to_shown',
        'is_con_box_rpt_need_to_shown',
        'is_box_tot_rpt_need_to_shown',
        'is_ob_cb_rpt_need_to_shown',
        'is_gallery_need_to_shown',
        'is_worker_need_to_shown',
        'is_emp_group_task_need_to_shown',
        'is_day_grand_rpt_need_shown',
        'is_need_pink_box_shown',
        'is_need_order_status_shown',
        'is_need_role_wise_cash_rpt_shown',
        'need_roles_in_rpt_shown',
        'is_need_to_retailer_shown',
        'is_need_grosswgt_print_shown',
        'is_cus_fitem_pur_out_shown',
        'is_cus_fitem_pur_in_shown',
        'is_need_show_order_display_in_head_login',
        'is_metal_stock_shown',

        'joining_date',

        // HEAD user form (2026-11-21).
        'aadhar_number',
        'aadhar_back_image',
        'image_url', // the form's "Image" (legacy "Image Url"), a stored file path
        'per_day_salary',
        'report_password',
        'is_item_change_need_shown',
        'is_item_conversion_need_shown',
        'is_phone_book_need_shown',
        // SALARY user form (2026-11-23).
        'additional_phone_no',
        // EMPLOYEE user form (2026-11-25).
        'alloy_value',
        'is_outside_vendor',
        'grams_grand_total',
        'purity_grand_total',
        'rak_cash_balance',
        'rak_rtgs_balance',
        'cash_balance',
        'rtgs_balance',
    ];

    protected $hidden = [
        'password_hash',
        'report_password',
        'otp',
    ];

    /**
     * Balance columns. Only heads see other users' values; everyone sees
     * their own (agreed 2026-11-12, see App\Support\BalanceVisibility).
     */
    public const BALANCE_FIELDS = [
        'grams_grand_total',
        'purity_grand_total',
        'stone_weight_grand_total',
        'beads_weight_grand_total',
        'net_weight_grand_total',
        'gross_weight_grand_total',
        'beads_stone_weight_grand_total',
        'stone_cost_grand_total',
        'beads_cost_grand_total',
        'beads_stone_cost_grand_total',
        'order_grand_total',
        'rak_cash_balance',
        'rak_rtgs_balance',
        'cash_balance',
        'rtgs_balance',
    ];

    public function toArray()
    {
        $data = parent::toArray();

        if (!\App\Support\BalanceVisibility::canSeeBalancesOf($this->getKey())) {
            foreach (self::BALANCE_FIELDS as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    protected $casts = [

        'role_id' => 'integer',

        'joining_date' => 'date:Y-m-d',

        'is_item_change_need_shown' => 'boolean',
        'is_item_conversion_need_shown' => 'boolean',
        'is_phone_book_need_shown' => 'boolean',
        'is_outside_vendor' => 'boolean',

        'is_act_as_head_enabled' => 'boolean',

        'is_active' => 'boolean',
        'is_delete' => 'boolean',
        'is_billable' => 'boolean',

        'is_customerfitem_cal_enabled' => 'boolean',
        'is_customerfitem_cal_in_enabled' => 'boolean',
        'is_create_order_shown' => 'boolean',

        'grams_grand_total' => 'float',
        'purity_grand_total' => 'float',
        'stone_weight_grand_total' => 'float',
        'beads_weight_grand_total' => 'float',
        'net_weight_grand_total' => 'float',
        'gross_weight_grand_total' => 'float',
        'beads_stone_weight_grand_total' => 'float',

        'stone_cost_grand_total' => 'float',
        'beads_cost_grand_total' => 'float',
        'beads_stone_cost_grand_total' => 'float',

        'order_grand_total' => 'float',
        'per_day_salary' => 'float',
        'rak_cash_balance' => 'float',
        'rak_rtgs_balance' => 'float',

        'allot_value' => 'float',

        'is_salary_person' => 'boolean',
        'is_gold_cal_enabled' => 'boolean',
        'is_cash_cal_enabled' => 'boolean',
        'is_wastage_cal_enabled' => 'boolean',
        'is_otp_verified' => 'boolean',

        'added_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    /*
    |--------------------------------------------------------------------------
    | ITEM MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function itemMappings()
    {
        return $this->hasMany(
            UsersItemsMapping::class,
            'user_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HEAD EMPLOYEE MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function headEmployeeMappings()
    {
        return $this->hasMany(
            HeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CASH HEAD EMPLOYEE MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function cashHeadEmployeeMappings()
    {
        return $this->hasMany(
            CashHeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER TOUCH MAPPINGS
    |--------------------------------------------------------------------------
    | The touches this user is given (customer_touch_user_mappings). Set on
    | the user form's "Customer touches" section.
    */

    /*
    | The users under this head (head_employee_mappings where this user is
    | the head) — the HEAD form's "Head Users". headEmployeeMappings() above
    | is the other direction: the heads this user reports to.
    */
    public function headUserMappings()
    {
        return $this->hasMany(
            HeadEmployeeMapping::class,
            'head_id',
            'user_id'
        );
    }

    public function customerTouchMappings()
    {
        return $this->hasMany(
            CustomerTouchUserMapping::class,
            'user_id',
            'user_id'
        );
    }

    public function usersItemsMappings(): HasMany
    {
        return $this->hasMany(
            UsersItemsMapping::class,
            'user_id',
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE MAPPING
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /*
    |--------------------------------------------------------------------------
    | Balance Bridges (No Prefix)
    |--------------------------------------------------------------------------
    */

    public function getCashBalanceAttribute()
    {
        return $this->rak_cash_balance;
    }

    public function setCashBalanceAttribute($value)
    {
        $this->rak_cash_balance = $value;
    }

    public function getRtgsBalanceAttribute()
    {
        return $this->rak_rtgs_balance;
    }

    public function setRtgsBalanceAttribute($value)
    {
        $this->rak_rtgs_balance = $value;
    }
}