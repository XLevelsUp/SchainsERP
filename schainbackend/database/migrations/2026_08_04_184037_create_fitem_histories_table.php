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
        Schema::create('fitem_histories', function (Blueprint $table) {
            $table->id();
            $table->decimal('grams', 12, 4);
            $table->decimal('touch', 12, 4);
            $table->decimal('purity', 12, 4);
            $table->decimal('mtouch', 12, 4);
            $table->decimal('wastage', 12, 4);
            $table->decimal('total', 12, 4);
            $table->string('fitem_type', 45);
            $table->unsignedBigInteger('fitem_stock_out_id');
            $table->unsignedBigInteger('box_id')->nullable();
            $table->timestamps();

            $table->foreign('fitem_stock_out_id')->references('stock_id')->on('stock_details')->onDelete('cascade');
            $table->foreign('box_id')->references('box_id')->on('fitem_boxes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fitem_histories');
    }
};
