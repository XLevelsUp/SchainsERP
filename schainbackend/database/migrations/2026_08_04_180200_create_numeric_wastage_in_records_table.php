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
        Schema::create('numeric_wastage_in_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->decimal('grams', 12, 4);
            $table->decimal('touch', 12, 4);
            $table->decimal('no_of_pcs', 12, 4);
            $table->integer('wastage_id')->nullable();
            $table->decimal('wastage_value', 12, 4);
            $table->decimal('wastage_total', 12, 4);
            $table->string('type', 45)->default('IN');
            $table->unsignedBigInteger('stock_in_detail_id')->nullable(); // references stock_in_details primary key
            $table->decimal('amount', 12, 4)->default(0);
            $table->unsignedBigInteger('cash_txn_id')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('stock_in_detail_id')->references('stock_in_detail_id')->on('stock_in_details')->onDelete('cascade');
            $table->foreign('cash_txn_id')->references('txn_id')->on('cash_txn_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numeric_wastage_in_records');
    }
};
