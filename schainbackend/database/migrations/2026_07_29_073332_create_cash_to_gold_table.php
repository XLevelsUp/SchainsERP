<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_to_gold', function (Blueprint $table) {

            $table->id('cash_to_gold_id');

            $table->enum('type', [
                'CUSTOMER',
                'HEAD',
                'SALE_GOLD',
                'GOLD_TO_CASH',
                'IN_CASH_CONVERTER',
                'OUT_CASH_CONVERTER',
                'CashToGold',
                'SALE_GOLD_CASH'
            ]);

            $table->unsignedBigInteger('head_id');

            $table->unsignedBigInteger('customer_id')
                ->nullable();

            $table->double('total_cash');

            $table->float('per_gram_cash');

            $table->float('total_grams');

            $table->float('touch');

            $table->float('purity');

            $table->unsignedBigInteger('item_id');

            $table->unsignedBigInteger('stock_id')
                ->nullable();

            $table->timestamp('added_at')
                ->useCurrent();

            $table->unsignedBigInteger('added_by')
                ->nullable();

            $table->boolean('amnt_transfer_to_head')
                ->default(true);

            $table->float('taken_total_cash')
                ->default(0);

            $table->float('taken_total_grams')
                ->default(0);

            $table->float('taken_purity')
                ->default(0);

            $table->double('ob_grams')
                ->nullable();

            $table->double('ob_purity')
                ->nullable();

            $table->string('remarks', 5000)
                ->nullable();

            $table->unsignedBigInteger('retailer_id')
                ->nullable();

            $table->boolean('is_rate_avg')
                ->default(false);

            $table->double('partial_amount')
                ->nullable();

            $table->double('balance_amount')
                ->nullable();

            $table->double('adjust_cash')
                ->nullable();

            $table->index('head_id');
            $table->index('customer_id');
            $table->index('item_id');
            $table->index('stock_id');
            $table->index('retailer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_to_gold');
    }
};