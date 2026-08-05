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
        Schema::create('gold_conversion_alloys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversion_id');
            $table->unsignedInteger('alloy_item_id');
            $table->decimal('alloy_percentage', 12, 4);
            $table->decimal('alloy_grams', 12, 4);
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('conversion_id')->references('id')->on('gold_conversions')->onDelete('cascade');
            $table->foreign('alloy_item_id')->references('item_id')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gold_conversion_alloys');
    }
};
