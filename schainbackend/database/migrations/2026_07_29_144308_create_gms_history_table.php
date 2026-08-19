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
        Schema::create('gms_history', function (Blueprint $table) {
            $table->id('gms_id');
            $table->unsignedInteger('item_id');
            $table->decimal('grams', 12, 4)->default(0);
            $table->decimal('stone', 12, 4)->default(0);
            $table->decimal('thread', 12, 4)->default(0);
            $table->decimal('wastage', 12, 4)->default(0);
            $table->decimal('hall_mark', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->decimal('mtouch', 12, 4)->default(0);
            $table->decimal('mtouch_wastage', 12, 4)->default(0);
            $table->decimal('to_mtouch', 12, 4)->default(0);
            $table->decimal('to_mtouch_wastage', 12, 4)->default(0);
            $table->decimal('to_stone', 12, 4)->default(0);
            $table->decimal('to_thread', 12, 4)->default(0);
            $table->decimal('to_wastage', 12, 4)->default(0);
            $table->decimal('to_hall_mark', 12, 4)->default(0);
            $table->decimal('to_total', 12, 4)->default(0);
            $table->unsignedInteger('to_item_id')->nullable();
            $table->string('gms_type', 45)->nullable();
            $table->unsignedBigInteger('gms_stock_in_id')->nullable();
            $table->unsignedBigInteger('gms_stock_out_id')->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('to_item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('gms_stock_in_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('gms_stock_out_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gms_history');
    }
};
