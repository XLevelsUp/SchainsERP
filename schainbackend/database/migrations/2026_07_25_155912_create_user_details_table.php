<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_details', function (Blueprint $table) {

            $table->bigIncrements('user_id');

            $table->string('name', 55);
            $table->string('user_name', 55)->unique();

            // Store Laravel Hash::make() result here
            $table->string('password_hash', 255);

            $table->string('address', 400);
            $table->string('signature', 45);
            $table->string('code', 255);
            $table->string('phone_no', 15)->unique();

            $table->string('remarks', 255)->nullable();
            $table->string('proff', 155);

            $table->string('role_id', 50);

            $table->string('customer_commants', 1500)->nullable();
            $table->string('mailing_name', 255);

            $table->string('image_url', 255)->nullable();
            $table->text('profile_image')->nullable();
            $table->string('aadhar_image', 255)->nullable();
            $table->enum('category_name', [
                'GRAMS',
                'PURITY',
                'BOTH'
            ]);

            $table->string('system_id', 255)->unique();

            $table->timestamp('updated_at')
                ->useCurrent();

            $table->timestamp('added_at')
                ->useCurrent();

            $table->boolean('is_active')
                ->default(true);

            $table->boolean('is_delete')
                ->default(false);

            $table->boolean('is_billable')
                ->default(false);

            $table->boolean('is_create_order_shown')
                ->default(false);

            $table->double('grams_grand_total')
                ->default(0);

            $table->double('purity_grand_total')
                ->default(0);

            $table->double('stone_weight_grand_total')
                ->nullable();

            $table->double('beads_weight_grand_total')
                ->nullable();

            $table->double('net_weight_grand_total')
                ->nullable();

            $table->double('gross_weight_grand_total')
                ->nullable();

            $table->double('beads_stone_weight_grand_total')
                ->nullable();

            $table->double('stone_cost_grand_total')
                ->nullable();

            $table->double('beads_cost_grand_total')
                ->nullable();

            $table->double('beads_stone_cost_grand_total')
                ->nullable();

            $table->double('order_grand_total')
                ->default(0);

            $table->integer('order_grand_no_of_pcs')
                ->default(0);

            $table->dateTime('last_txn_date')
                ->nullable();

            $table->boolean('is_salary_person')
                ->default(false);

            $table->double('per_day_salary')
                ->default(0);

            $table->double('rak_cash_balance')
                ->default(0);

            $table->date('last_cash_txn_date')
                ->nullable();

            $table->boolean('is_gold_cal_enabled')
                ->default(true);

            $table->boolean('is_cash_cal_enabled')
                ->default(true);

            $table->boolean('is_wastage_cal_enabled')
                ->default(true);

            $table->boolean('is_customerfitem_cal_enabled');

            $table->boolean('is_customerfitem_cal_in_enabled');

            $table->string('report_password', 250)
                ->nullable();

            $table->string('otp', 4)
                ->nullable();

            $table->boolean('is_otp_verified')
                ->default(false);

            $table->double('allot_value')
                ->nullable();

            $table->boolean('is_remainder_shown')
                ->nullable();

            $table->boolean('is_delivery_item_shown')
                ->nullable();

            $table->double('rak_rtgs_balance')
                ->default(0);

            $table->dateTime('last_rtgs_txn_date')
                ->nullable();

            $table->boolean('is_polish_needed')
                ->default(false);

            $table->boolean('is_wa_delivery_stock_needed')
                ->default(false);

            $table->boolean('is_polish_chk_need_shown')
                ->default(false);

            $table->boolean('is_delivery_chk_need_shown')
                ->default(false);

            $table->boolean('is_cashamt_thermal_shown')
                ->default(false);

            $table->boolean('is_customer_touch_need_shown')
                ->default(false);

            $table->boolean('is_complete_history_need_shown')
                ->default(false);

            $table->boolean('is_create_order_need_to_shown')
                ->default(false);

            $table->boolean('is_cash_mngmt_need_to_shown')
                ->default(false);

            $table->boolean('is_freeze_entry_need_to_shown')
                ->default(false);

            $table->boolean('is_admin_login_otp_need_to_shown')
                ->default(false);

            $table->boolean('is_customer_cmts_need_to_shown')
                ->default(false);

            $table->boolean('is_outside_need_to_shown')
                ->default(false);

            $table->boolean('is_tally_need_to_shown')
                ->default(false);

            $table->boolean('is_die_num_search_need_to_shown')
                ->default(false);

            $table->boolean('is_con_box_rpt_need_to_shown')
                ->default(false);

            $table->boolean('is_box_tot_rpt_need_to_shown')
                ->default(false);

            $table->boolean('is_ob_cb_rpt_need_to_shown')
                ->default(false);

            $table->boolean('is_gallery_need_to_shown')
                ->default(false);

            $table->boolean('is_worker_need_to_shown')
                ->default(false);

            $table->boolean('is_emp_group_task_need_to_shown')
                ->default(false);

            $table->boolean('is_day_grand_rpt_need_shown')
                ->default(false);

            $table->boolean('is_need_pink_box_shown')
                ->default(false);

            $table->boolean('is_need_order_status_shown')
                ->default(false);

            $table->boolean('is_need_role_wise_cash_rpt_shown')
                ->default(false);

            $table->string('need_roles_in_rpt_shown', 2000)
                ->nullable();

            $table->boolean('is_need_to_retailer_shown')
                ->default(false);

            $table->boolean('is_need_grosswgt_print_shown')
                ->default(false);

            $table->boolean('is_cus_fitem_pur_out_shown')
                ->default(false);

            $table->boolean('is_cus_fitem_pur_in_shown')
                ->default(false);

            $table->boolean('is_need_show_order_display_in_head_login')
                ->nullable();

            $table->boolean('is_metal_stock_shown')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};