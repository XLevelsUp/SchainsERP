<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_to_gold_amount_sources', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('cash_to_gold_id')
                ->nullable();

            $table->unsignedBigInteger('cash_txn_id')
                ->nullable();

            $table->enum('souce_type', [
                'CASH_ON_HAND',
                'BANK'
            ])->default('CASH_ON_HAND');

            $table->unsignedBigInteger('bank_id')
                ->nullable();

            $table->double('amount');

            $table->timestamp('added_at')
                ->useCurrent();

            $table->index('cash_to_gold_id');
            $table->index('bank_id');

            /*
            |--------------------------------------------------------------------------
            | Add FK only after confirming the referenced tables
            |--------------------------------------------------------------------------
            */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_to_gold_amount_sources');
    }
};