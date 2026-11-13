<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correcting gold entries (BACKEND_TODO §17 phase 3, agreed 2026-11-12).
 * A head's correction never rewrites the original. It posts a reversal (the
 * original with its amounts negated) and the corrected entry, and links the
 * three:
 *
 *   original.corrected_by_id  → the corrected entry
 *   reversal.reversal_of_id   → the original
 *   corrected.corrects_id     → the original
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_to_gold', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
            $table->unsignedBigInteger('corrects_id')->nullable()->index();
            $table->unsignedBigInteger('corrected_by_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('cash_to_gold', function (Blueprint $table) {
            $table->dropIndex(['reversal_of_id']);
            $table->dropIndex(['corrects_id']);
            $table->dropIndex(['corrected_by_id']);
            $table->dropColumn(['reversal_of_id', 'corrects_id', 'corrected_by_id']);
        });
    }
};
