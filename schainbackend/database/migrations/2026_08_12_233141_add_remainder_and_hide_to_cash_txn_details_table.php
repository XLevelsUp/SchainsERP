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
        Schema::table('cash_txn_details', function (Blueprint $table) {
            $table->string('remainder')->nullable()->after('remarks');
            $table->dateTime('remainder_at')->nullable()->after('remainder');
            $table->boolean('is_hide')->default(false)->after('remainder_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_txn_details', function (Blueprint $table) {
            $table->dropColumn(['remainder', 'remainder_at', 'is_hide']);
        });
    }
};
