<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_in_details', function (Blueprint $table) {
            $table->id('stock_in_detail_id');
            $table->unsignedInteger('item_id');
            $table->unsignedBigInteger('given_by');
            $table->unsignedBigInteger('given_to');
            $table->enum('type', ['NORMAL', 'ITEMCHANGE', 'ITEMCONVERSION', 'GMS', 'FITEM', 'NUMERICWASTE'])->default('NORMAL');
            $table->enum('entry_type', ['NORMAL', 'EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD', 'GOLDCASHCONVERSION', 'OUT_CASH_CONVERTER', 'CashToGold', 'SALES'])->default('NORMAL');
            $table->enum('stock_type', ['IN', 'OUT'])->default('IN');
            $table->decimal('grams', 12, 4);
            $table->decimal('touch', 12, 4);
            $table->decimal('purity', 12, 4);
            $table->text('remarks')->nullable();
            $table->decimal('waste_total', 12, 4)->nullable();
            $table->decimal('waste_value', 12, 4)->nullable();
            $table->decimal('mtouch', 12, 4)->nullable();
            $table->decimal('gms_mtouch', 12, 4)->nullable();
            $table->decimal('gms_mthouch_wastage', 12, 4)->nullable();
            $table->unsignedInteger('waste_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->decimal('balance', 12, 4);
            $table->unsignedBigInteger('stock_in_id')->nullable(); // references original outward stock row
            $table->boolean('is_freezed')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_receiver_completed')->default(false);
            $table->boolean('is_hided')->default(false);
            $table->unsignedBigInteger('added_by');
            $table->unsignedInteger('to_item_id')->nullable();
            
            // Opening Balances
            $table->decimal('given_by_item_grams_op', 12, 4)->nullable();
            $table->decimal('given_to_item_grams_op', 12, 4)->nullable();
            $table->decimal('given_by_item_purity_op', 12, 4)->nullable();
            $table->decimal('given_to_item_purity_op', 12, 4)->nullable();
            
            // Closing Balances
            $table->decimal('given_by_item_grams_cb', 12, 4)->nullable();
            $table->decimal('given_to_item_grams_cb', 12, 4)->nullable();
            $table->decimal('given_by_item_purity_cb', 12, 4)->nullable();
            $table->decimal('given_to_item_purity_cb', 12, 4)->nullable();
            
            $table->text('item_remarks')->nullable();
            $table->decimal('stock_in_ob', 12, 4)->nullable();
            $table->decimal('stock_in_cb', 12, 4)->nullable();
            $table->decimal('stock_in_ob_purity', 12, 4)->nullable();
            $table->decimal('stock_in_cb_purity', 12, 4)->nullable();
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->decimal('retailer_op_grams', 12, 4)->default(0);
            $table->decimal('retailer_op_purity', 12, 4)->default(0);
            $table->unsignedBigInteger('to_retailer_id')->nullable();
            $table->decimal('to_retailer_op_grams', 12, 4)->default(0);
            $table->decimal('to_retailer_op_purity', 12, 4)->default(0);
            $table->jsonb('reply_history_json')->nullable();
            $table->jsonb('obcb_details')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['is_hided', 'is_freezed', 'is_completed'], 'idx_in_main');
            $table->index(['entry_type', 'stock_type'], 'idx_in_entry');
            $table->index(['given_to', 'given_by'], 'idx_in_user');
            $table->index(['retailer_id', 'to_retailer_id'], 'idx_in_retailer');

            // Foreign Keys
            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('restrict');
            $table->foreign('given_by')->references('user_id')->on('user_details')->onDelete('restrict');
            $table->foreign('given_to')->references('user_id')->on('user_details')->onDelete('restrict');
            $table->foreign('bill_id')->references('bill_id')->on('billing_entries')->onDelete('restrict');
            $table->foreign('stock_in_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('added_by')->references('user_id')->on('user_details')->onDelete('restrict');
            $table->foreign('to_item_id')->references('item_id')->on('items')->onDelete('restrict');
            $table->foreign('waste_id')->references('waste_id')->on('wastage_details')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_details');
    }
};
