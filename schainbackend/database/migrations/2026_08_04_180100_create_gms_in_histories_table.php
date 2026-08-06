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
        Schema::create('gms_in_histories', function (Blueprint $table) {
            $table->id('gms_id');
            $table->unsignedInteger('item_id');
            $table->decimal('grams', 12, 4)->default(0);
            $table->decimal('stone', 12, 4)->default(0);
            $table->decimal('thread', 12, 4)->default(0);
            $table->decimal('mtouch', 12, 4)->default(0);
            $table->decimal('mtouch_wastage', 12, 4)->default(0);
            $table->decimal('wastage', 12, 4)->default(0);
            $table->decimal('hall_mark', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->string('gms_type', 45)->default('IN');
            $table->unsignedBigInteger('gms_stock_in_id')->nullable(); // references stock_in_details primary key
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->foreign('gms_stock_in_id')->references('stock_in_detail_id')->on('stock_in_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gms_in_histories');
    }
};
