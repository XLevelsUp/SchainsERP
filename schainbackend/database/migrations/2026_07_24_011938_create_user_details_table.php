<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_details', function (Blueprint $table) {

            // Primary Key
            $table->id('user_id');

            // Basic User Information
            $table->string('name', 55);
            $table->string('user_name', 55)->unique();

            // Laravel password hash can be more than 32 characters
            $table->string('password_hash', 255);

            $table->string('address', 400);
            $table->string('signature', 45);
            $table->string('code', 255);

            $table->string('phone_no', 15)->unique();

            $table->string('remarks', 255)->nullable();
            $table->string('proff', 155);
            $table->string('role', 50);

            $table->string('customer_commants', 1500)->nullable();
            $table->string('mailing_name', 255);

            $table->string('image_url', 255)->nullable();
            $table->text('profile_image')->nullable();

            // MySQL ENUM converted to PostgreSQL-compatible VARCHAR
            $table->string('category_name', 20);

            $table->string('system_id', 255)->unique();

            // Date / Time
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('added_at')->useCurrent();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_delete')->default(false);
            $table->boolean('is_billable')->default(false);
            $table->boolean('is_create_order_shown')->default(false);

            // Grand Totals
            $table->double('grams_grand_total')->default(0);
            $table->double('purity_grand_total')->default(0);

            $table->double('stone_weight_grand_total')->nullable();
            $table->double('beads_weight_grand_total')->nullable();
            $table->double('net_weight_grand_total')->nullable();
            $table->double('gross_weight_grand_total')->nullable();

            $table->double('beads_stone_weight_grand_total')->nullable();
            $table->double('stone_cost_grand_total')->nullable();
            $table->double('beads_cost_grand_total')->nullable();
            $table->double('beads_stone_cost_grand_total')->nullable();

            $table->double('order_grand_total')->default(0);
            $table->integer('order_grand_no_of_pcs')->default(0);

            // Transaction
            $table->timestamp('last_txn_date')->nullable();

            // Salary
            $table->boolean('is_salary_person')->default(false);
            $table->double('per_day_salary')->default(0);

            // Cash
            $table->double('rak_cash_balance')->default(0);
            $table->date('last_cash_txn_date')->nullable();

            // Calculation Settings
            $table->boolean('is_gold_cal_enabled')->default(true);
            $table->boolean('is_cash_cal_enabled')->default(true);
            $table->boolean('is_wastage_cal_enabled')->default(true);

            $table->boolean('is_customerfitem_cal_enabled');
            $table->boolean('is_customerfitem_cal_in_enabled');

            // Password / OTP
            $table->string('report_password', 250)->nullable();
            $table->string('otp', 4)->nullable();

            $table->boolean('is_otp_verified')->default(false);

            // Display Settings
            $table->double('allot_value')->nullable();

            $table->boolean('is_remainder_shown')->nullable();
            $table->boolean('is_delivery_item_shown')->nullable();

            // RTGS
            $table->double('rak_rtgs_balance')->default(0);
            $table->timestamp('last_rtgs_txn_date')->nullable();

            // Feature Settings
            $table->boolean('is_polish_needed')->default(false);
            $table->boolean('is_wa_delivery_stock_needed')->default(false);
            $table->boolean('is_polish_chk_need_shown')->default(false);
            $table->boolean('is_delivery_chk_need_shown')->default(false);

            $table->boolean('is_cashamt_thermal_shown')->default(false);
            $table->boolean('is_customer_touch_need_shown')->default(false);
            $table->boolean('is_complete_history_need_shown')->default(false);
            $table->boolean('is_create_order_need_to_shown')->default(false);
            $table->boolean('is_cash_mngmt_need_to_shown')->default(false);
            $table->boolean('is_freeze_entry_need_to_shown')->default(false);
            $table->boolean('is_admin_login_otp_need_to_shown')->default(false);
            $table->boolean('is_customer_cmts_need_to_shown')->default(false);
            $table->boolean('is_outside_need_to_shown')->default(false);
            $table->boolean('is_tally_need_to_shown')->default(false);
            $table->boolean('is_die_num_search_need_to_shown')->default(false);

            $table->boolean('is_con_box_rpt_need_to_shown')->default(false);
            $table->boolean('is_box_tot_rpt_need_to_shown')->default(false);
            $table->boolean('is_ob_cb_rpt_need_to_shown')->default(false);
            $table->boolean('is_gallery_need_to_shown')->default(false);
            $table->boolean('is_worker_need_to_shown')->default(false);
            $table->boolean('is_emp_group_task_need_to_shown')->default(false);
            $table->boolean('is_day_grand_rpt_need_shown')->default(false);

            $table->boolean('is_need_pink_box_shown')->default(false);
            $table->boolean('is_need_order_status_shown')->default(false);
            $table->boolean('is_need_role_wise_cash_rpt_shown')->default(false);

            $table->text('need_roles_in_rpt_shown')->nullable();

            $table->boolean('is_need_to_retailer_shown')->default(false);
            $table->boolean('is_need_grosswgt_print_shown')->default(false);
            $table->boolean('is_cus_fitem_pur_out_shown')->default(false);
            $table->boolean('is_cus_fitem_pur_in_shown')->default(false);

            $table->boolean('is_need_show_order_display_in_head_login')
                ->nullable();

            // User References
            $table->integer('attendance_user_id')->nullable();

            $table->boolean('is_metal_stock_shown')->nullable();

            $table->string('top_print_out', 100)->nullable();

            $table->integer('incentive_sent_to')->nullable();

            $table->integer('vendor_barcode_group_id')->nullable();

            $table->string('vendor_short_name', 100)->nullable();

            // Credit Limits
            $table->double('credit_out_limit')->nullable();
            $table->double('credit_in_limit')->nullable();

            $table->text('credit_limit_remarks')->nullable();

            $table->timestamp('credit_limit_updated_at')->nullable();

            $table->integer('credit_limit_updated_by')->nullable();

            $table->double('temp_gram_credit_limit')->nullable();
            $table->double('temp_purity_credit_limit')->nullable();

            // MC Choice
            $table->string('default_mc_choice', 20)->nullable();

            // PostgreSQL TEXT instead of MySQL LONGTEXT
            $table->text('page_access')->nullable();

            // Head Information
            $table->integer('is_admin_head')->default(0);

            $table->integer('main_head_id')->nullable();
        });

        // Add PostgreSQL CHECK constraints
        DB::statement("
            ALTER TABLE user_details
            ADD CONSTRAINT user_details_category_name_check
            CHECK (category_name IN ('GRAMS', 'PURITY', 'BOTH'))
        ");

        DB::statement("
            ALTER TABLE user_details
            ADD CONSTRAINT user_details_default_mc_choice_check
            CHECK (
                default_mc_choice IS NULL
                OR default_mc_choice IN ('RETAILER', 'WHOLESALER')
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};