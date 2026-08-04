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
        Schema::table('stock_details', function (Blueprint $table) {
            $table->decimal('given_by_item_grams_cb', 12, 4)->nullable();
            $table->decimal('given_to_item_grams_cb', 12, 4)->nullable();
            $table->decimal('given_by_item_purity_cb', 12, 4)->nullable();
            $table->decimal('given_to_item_purity_cb', 12, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_details', function (Blueprint $table) {
            $table->dropColumn([
                'given_by_item_grams_cb',
                'given_to_item_grams_cb',
                'given_by_item_purity_cb',
                'given_to_item_purity_cb',
            ]);
        });
    }
};
