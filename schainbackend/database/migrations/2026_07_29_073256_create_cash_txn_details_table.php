<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_txn_details', function (Blueprint $table) {

            $table->bigIncrements('txn_id');

            $table->string('type', 50);

            $table->integer('given_to');

            $table->integer('given_by');

            $table->integer('category_id')->nullable();

            $table->double('amount');

            $table->double('balance')->nullable();

            $table->double('opening_account_balance');

            $table->double('opening_user_balance');

            $table->double('opening_bank_account_balance')
                  ->default(0);

            $table->double('opening_bank_user_balance')
                  ->default(0);

            $table->double('closing_account_balance')
                  ->default(0);

            $table->double('closing_user_balance')
                  ->default(0);

            $table->double('closing_bank_account_balance')
                  ->default(0);

            $table->double('closing_bank_user_balance')
                  ->default(0);

            $table->string('souce_type', 30)
                  ->default('CASH_ON_HAND');

            $table->integer('bank_id')->nullable();

            $table->string('remarks', 5000)->nullable();

            $table->string('remainder', 1500)->nullable();

            $table->timestamp('remainder_at')->nullable();

            $table->timestamp('added_at')
                  ->useCurrent();

            $table->integer('added_by');

            $table->boolean('is_active')
                  ->default(true);

            $table->boolean('is_hidden')
                  ->default(false);

            $table->boolean('is_show_to_all')
                  ->default(false);

            $table->integer('amount_transfer_id')->nullable();

            $table->string('image_url', 150)->nullable();

            $table->integer('cash_to_gold_id')->nullable();

            $table->integer('stock_id')->nullable();

            $table->boolean('amnt_transfer_from_head')
                  ->default(true);

            $table->string('internal_type', 50)->nullable();

            $table->integer('retailer_id')->nullable();

            $table->double('retailer_ob_cash_balance')
                  ->nullable();

            $table->double('retailer_ob_rtgs_balance')
                  ->nullable();

            $table->string('txn_type', 30)
                  ->default('NORMAL');

            $table->date('bank_entry_date')->nullable();

            $table->integer('machine_vendor_id')->nullable();

            $table->double('machine_vendor_ob_cash_balance')
                  ->nullable();

            $table->double('machine_vendor_ob_rtgs_balance')
                  ->nullable();

            $table->boolean('is_bill_cash')->nullable();

            $table->boolean('is_payment_cash')->nullable();

            $table->boolean('is_customer_affect')->nullable();

            $table->boolean('is_need_receipt')->nullable();

            $table->string('bill_payment_cash_type', 50)
                  ->nullable();

            $table->double('partial_amount')->nullable();

            $table->double('actual_amount')->nullable();

            $table->integer('receipt_cash_txn_id')->nullable();

            $table->double('rate_of_interest')->nullable();

            $table->string(
                'given_by_arithmetic_operation',
                1
            )->nullable();

            $table->string(
                'given_to_arithmetic_operation',
                1
            )->nullable();

            $table->string('cash_loan_type', 50)
                  ->nullable();

            $table->double('per_gram_cash')->nullable();

            $table->text('rate_avg_details')->nullable();

            $table->boolean('is_rate_avg_subtract')
                  ->nullable();

            $table->boolean('is_rate_avg')
                  ->default(false);

            $table->integer('over_all_bill_id')->nullable();

            $table->integer('estimate_retailer_bill_id')
                  ->nullable();

            $table->integer('estimate_metal_bill_id')
                  ->nullable();

            $table->boolean('is_admin_head_entry')
                  ->default(false);

            $table->integer('admin_head_txn_id')
                  ->nullable();

            // Indexes
            $table->index('given_to');
            $table->index('given_by');
            $table->index('category_id');
            $table->index('added_by');
            $table->index('bank_id');
            $table->index('amount_transfer_id');
            $table->index('cash_to_gold_id');
            $table->index('stock_id');
            $table->index('retailer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_txn_details');
    }
};