<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class UserDetail extends Model
{
    protected $table = 'user_details';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

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
        'role_id',
        'customer_commants',
        'mailing_name',
        'image_url',
        'profile_image',
        'aadhar_image',
        'category_name',
        'system_id',

        'is_active',
        'is_delete',
        'is_billable',
        'is_create_order_shown',

        'is_salary_person',
        'per_day_salary',

        'is_gold_cal_enabled',
        'is_cash_cal_enabled',
        'is_wastage_cal_enabled',

        'is_customerfitem_cal_enabled',
        'is_customerfitem_cal_in_enabled',

        'report_password',
        'otp',
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
    ];

    protected $hidden = [
        'password_hash',
        'report_password',
        'otp',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_delete' => 'boolean',
        'is_billable' => 'boolean',
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

        'is_customerfitem_cal_enabled' => 'boolean',
        'is_customerfitem_cal_in_enabled' => 'boolean',

        'is_otp_verified' => 'boolean',

        'is_polish_needed' => 'boolean',
        'is_wa_delivery_stock_needed' => 'boolean',
        'is_polish_chk_need_shown' => 'boolean',
        'is_delivery_chk_need_shown' => 'boolean',
        'is_cashamt_thermal_shown' => 'boolean',
        'is_customer_touch_need_shown' => 'boolean',
        'is_complete_history_need_shown' => 'boolean',
        'is_create_order_need_to_shown' => 'boolean',
        'is_cash_mngmt_need_to_shown' => 'boolean',
        'is_freeze_entry_need_to_shown' => 'boolean',
        'is_admin_login_otp_need_to_shown' => 'boolean',
        'is_customer_cmts_need_to_shown' => 'boolean',
        'is_outside_need_to_shown' => 'boolean',
        'is_tally_need_to_shown' => 'boolean',
        'is_die_num_search_need_to_shown' => 'boolean',
        'is_con_box_rpt_need_to_shown' => 'boolean',
        'is_box_tot_rpt_need_to_shown' => 'boolean',
        'is_ob_cb_rpt_need_to_shown' => 'boolean',
        'is_gallery_need_to_shown' => 'boolean',
        'is_worker_need_to_shown' => 'boolean',
        'is_emp_group_task_need_to_shown' => 'boolean',
        'is_day_grand_rpt_need_shown' => 'boolean',
        'is_need_pink_box_shown' => 'boolean',
        'is_need_order_status_shown' => 'boolean',
        'is_need_role_wise_cash_rpt_shown' => 'boolean',
        'is_need_to_retailer_shown' => 'boolean',
        'is_need_grosswgt_print_shown' => 'boolean',
        'is_cus_fitem_pur_out_shown' => 'boolean',
        'is_cus_fitem_pur_in_shown' => 'boolean',
        'is_need_show_order_display_in_head_login' => 'boolean',
        'is_metal_stock_shown' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function itemMappings(): HasMany
    {
        return $this->hasMany(
            UsersItemsMapping::class,
            'user_id',
            'user_id'
        );
    }

    public function headMappings(): HasMany
    {
        return $this->hasMany(
            HeadEmployeeMapping::class,
            'head_id',
            'user_id'
        );
    }

    public function employeeMappings(): HasMany
    {
        return $this->hasMany(
            HeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }

    public function cashHeadMappings(): HasMany
    {
        return $this->hasMany(
            CashHeadEmployeeMapping::class,
            'head_id',
            'user_id'
        );
    }

    public function cashEmployeeMappings(): HasMany
    {
        return $this->hasMany(
            CashHeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }
}