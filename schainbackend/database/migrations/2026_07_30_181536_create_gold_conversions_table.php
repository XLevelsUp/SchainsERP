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
        Schema::create('gold_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('source_item_id');
            $table->unsignedInteger('target_item_id');
            $table->decimal('source_grams', 12, 4);
            $table->decimal('source_touch', 12, 4);
            $table->decimal('target_touch', 12, 4);
            $table->decimal('converted_grams', 12, 4);
            $table->unsignedBigInteger('in_stock_id')->nullable();
            $table->unsignedBigInteger('out_stock_id')->nullable();
            $table->unsignedBigInteger('billing_entry_id')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('source_item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('target_item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('in_stock_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('out_stock_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('billing_entry_id')->references('bill_id')->on('billing_entries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_conversions');
    }
};
