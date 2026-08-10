<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HeadUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\UserDetail::create([
            'user_id' => 999,
            'name' => 'Demo Head User',
            'user_name' => 'demo_head',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('password'),
            'address' => '123 Main St',
            'signature' => 'DH',
            'code' => 'H001',
            'phone_no' => '00000000006',
            'remarks' => 'Dummy Head Account',
            'proff' => 'Head',
            'role_id' => 3, // HEAD role
            'mailing_name' => 'Demo Head',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_HEAD_1',
            'grams_grand_total' => 0.000,
            'purity_grand_total' => -53.725,
            'is_active' => true,
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_create_order_shown' => false,
            'is_remainder_shown' => false,
            'is_delivery_item_shown' => false,
            'is_need_role_wise_cash_rpt_shown' => false,
            'is_need_show_order_display_in_head_login' => false,
            'is_metal_stock_shown' => false,
        ]);
    }
}
