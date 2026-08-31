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
            // Added 28-08-2026 for Auto Entry From/To Touch changes parity with Yii2
            // Note: If they already exist (e.g. manually added during dev), this will prevent errors if we use Doctrine DBAL, but native Laravel doesn't support IF NOT EXISTS for columns in all drivers easily.
            // Since we know they are missing in the schema, we add them directly.
            $table->decimal('to_touch', 12, 4)->nullable();
            $table->decimal('to_purity', 12, 4)->nullable();
            $table->decimal('to_waste_value', 12, 4)->nullable();
            $table->unsignedInteger('to_waste_id')->nullable();
            $table->decimal('to_waste_total', 12, 4)->nullable();
            $table->decimal('to_mtouch', 12, 4)->nullable();

            // Index optimized for time-series date range scans proved by EXPLAIN ANALYZE
            $table->index(['added_at'], 'idx_stock_added_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_details', function (Blueprint $table) {
            $table->dropIndex('idx_stock_added_at');
            $table->dropColumn([
                'to_touch', 
                'to_purity', 
                'to_waste_value', 
                'to_waste_id', 
                'to_waste_total', 
                'to_mtouch'
            ]);
        });
    }
};
