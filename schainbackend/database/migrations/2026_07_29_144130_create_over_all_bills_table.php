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
        Schema::create('over_all_bills', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->double('mc')->nullable();
            $table->boolean('is_cash_updated')->default(false);
            $table->bigInteger('cash_txn_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('over_all_bills');
    }
};
