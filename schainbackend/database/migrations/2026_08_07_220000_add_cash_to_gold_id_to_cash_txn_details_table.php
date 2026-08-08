<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_txn_details', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_to_gold_id')->nullable()->after('added_by');
            $table->index('cash_to_gold_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_txn_details', function (Blueprint $table) {
            $table->dropIndex(['cash_to_gold_id']);
            $table->dropColumn('cash_to_gold_id');
        });
    }
};
