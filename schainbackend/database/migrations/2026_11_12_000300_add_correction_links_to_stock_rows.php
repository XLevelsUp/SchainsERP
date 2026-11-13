<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links between a corrected stock entry, its reversal and the corrected
 * entry (BACKEND_TODO §17, numeric wastage corrections). Same three columns
 * as cash_to_gold (2026_11_12_000200), on both stock tables:
 *
 *   corrected_by_id  on the original: the corrected entry that replaced it
 *   reversal_of_id   on the reversal: the original it cancels
 *   corrects_id      on the corrected entry: the original it replaces
 *
 * All nullable, so existing rows are untouched.
 */
return new class extends Migration
{
    private const TABLES = ['stock_details', 'stock_in_details'];

    private const COLUMNS = ['reversal_of_id', 'corrects_id', 'corrected_by_id'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (self::COLUMNS as $column) {
                    if (!Schema::hasColumn($table, $column)) {
                        $t->unsignedBigInteger($column)->nullable()->index();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (self::COLUMNS as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropIndex([$column]);
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }
};
