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
        Schema::create('item_change_history', function (Blueprint $table) {
            $table->id('change_id');
            $table->unsignedInteger('from_item_id');
            $table->unsignedInteger('to_item_id');
            $table->decimal('grams', 12, 4);
            $table->decimal('from_touch', 12, 4);
            $table->decimal('req_touch', 12, 4);
            $table->decimal('total', 12, 4);
            $table->string('change_type', 45)->nullable();
            $table->unsignedBigInteger('out_stock_id')->nullable();
            $table->unsignedBigInteger('in_stock_id')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('from_item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('to_item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('out_stock_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('in_stock_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_change_history');
    }
};
