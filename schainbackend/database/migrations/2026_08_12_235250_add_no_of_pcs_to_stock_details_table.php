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
            $table->decimal('no_of_pcs', 12, 4)->nullable()->after('grams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_details', function (Blueprint $table) {
            $table->dropColumn('no_of_pcs');
        });
    }
};
