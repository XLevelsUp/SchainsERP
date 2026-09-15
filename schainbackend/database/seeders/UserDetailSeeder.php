<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Hash;

class UserDetailSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'user_name'     => 'head_admin',
                'name'          => 'Head Admin',
                'password_hash' => Hash::make('password'),
                'address'       => 'Office',
                'signature'     => 'HA',
                'code'          => 'HA001',
                'phone_no'      => '9876543201',
                'remarks'       => 'Head admin user',
                'proff'         => 'Head',
                'role_id'       => 3,
                'mailing_name'  => 'Head Admin Mailing',
                'category_name' => 'BOTH',
                'system_id'     => 'SYS_HEAD',
                'is_active'     => true,
            ],
            [
                'user_name'     => 'employee_one',
                'name'          => 'Employee One',
                'password_hash' => Hash::make('password'),
                'address'       => 'Branch',
                'signature'     => 'E1',
                'code'          => 'E001',
                'phone_no'      => '9876543202',
                'remarks'       => 'Employee user',
                'proff'         => 'Employee',
                'role_id'       => 2,
                'mailing_name'  => 'Employee One Mailing',
                'category_name' => 'BOTH',
                'system_id'     => 'SYS_EMP1',
                'is_active'     => true,
            ],
            [
                'user_name'     => 'customer_one',
                'name'          => 'Customer One',
                'password_hash' => Hash::make('password'),
                'address'       => 'Chennai',
                'signature'     => 'C1',
                'code'          => 'C001',
                'phone_no'      => '9876543203',
                'remarks'       => 'Demo customer',
                'proff'         => 'Customer',
                'role_id'       => 1,
                'mailing_name'  => 'Customer One Mailing',
                'category_name' => 'GRAMS',
                'system_id'     => 'SYS_CUST1',
                'is_active'     => true,
            ],
            [
                'user_name'     => 'retailer_one',
                'name'          => 'Retailer One',
                'password_hash' => Hash::make('password'),
                'address'       => 'Coimbatore',
                'signature'     => 'R1',
                'code'          => 'R001',
                'phone_no'      => '9876543204',
                'remarks'       => 'Demo retailer',
                'proff'         => 'Retailer',
                'role_id'       => 4,
                'mailing_name'  => 'Retailer One Mailing',
                'category_name' => 'BOTH',
                'system_id'     => 'SYS_RET1',
                'is_active'     => true,
            ],
        ];

        foreach ($users as $userData) {
            UserDetail::firstOrCreate(
                ['user_name' => $userData['user_name']],
                array_merge($userData, [
                    'is_delete'                       => false,
                    'is_billable'                     => true,
                    'is_create_order_shown'           => false,
                    'is_customerfitem_cal_enabled'     => false,
                    'is_customerfitem_cal_in_enabled'  => false,
                    'is_remainder_shown'              => false,
                    'is_delivery_item_shown'          => false,
                    'is_salary_person'                => false,
                    'is_gold_cal_enabled'             => true,
                    'is_cash_cal_enabled'             => true,
                    'is_wastage_cal_enabled'          => true,
                    'is_otp_verified'                 => false,
                    'is_polish_needed'                => false,
                    'is_wa_delivery_stock_needed'     => false,
                    'is_polish_chk_need_shown'        => false,
                    'is_delivery_chk_need_shown'      => false,
                    'is_cashamt_thermal_shown'        => false,
                    'is_customer_touch_need_shown'    => false,
                    'is_complete_history_need_shown'  => false,
                    'is_create_order_need_to_shown'   => false,
                    'is_cash_mngmt_need_to_shown'     => false,
                    'is_freeze_entry_need_to_shown'   => false,
                    'is_admin_login_otp_need_to_shown' => false,
                    'is_customer_cmts_need_to_shown'  => false,
                    'is_outside_need_to_shown'        => false,
                    'is_tally_need_to_shown'          => false,
                    'is_die_num_search_need_to_shown' => false,
                    'is_con_box_rpt_need_to_shown'    => false,
                    'is_box_tot_rpt_need_to_shown'    => false,
                    'is_ob_cb_rpt_need_to_shown'      => false,
                    'is_gallery_need_to_shown'        => false,
                    'is_worker_need_to_shown'         => false,
                    'is_emp_group_task_need_to_shown' => false,
                    'is_day_grand_rpt_need_shown'     => false,
                    'is_need_pink_box_shown'          => false,
                    'is_need_order_status_shown'      => false,
                    'is_need_role_wise_cash_rpt_shown' => false,
                    'need_roles_in_rpt_shown'         => false,
                    'is_need_to_retailer_shown'       => false,
                    'is_need_grosswgt_print_shown'    => false,
                    'is_cus_fitem_pur_out_shown'      => false,
                    'is_cus_fitem_pur_in_shown'       => false,
                    'is_need_show_order_display_in_head_login' => false,
                    'is_metal_stock_shown'            => false,
                    'grams_grand_total'               => 0,
                    'purity_grand_total'              => 0,
                ])
            );
        }
    }
}
