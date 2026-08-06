<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_txn_details', function (Blueprint $table) {
            $table->bigIncrements('txn_id');
            $table->string('type', 50); // INCOME, EXPENSE
            $table->integer('sender_id');
            $table->integer('recipient_id');
            $table->integer('category_id')->nullable();
            
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after_txn', 15, 2)->nullable();
            
            $table->decimal('sender_opening_cash', 15, 2)->default(0.00);
            $table->decimal('sender_opening_rtgs', 15, 2)->default(0.00);
            $table->decimal('recipient_opening_cash', 15, 2)->default(0.00);
            $table->decimal('recipient_opening_rtgs', 15, 2)->default(0.00);
            
            $table->decimal('sender_closing_cash', 15, 2)->default(0.00);
            $table->decimal('sender_closing_rtgs', 15, 2)->default(0.00);
            $table->decimal('recipient_closing_cash', 15, 2)->default(0.00);
            $table->decimal('recipient_closing_rtgs', 15, 2)->default(0.00);
            
            $table->string('payment_method', 50)->default('CASH_ON_HAND'); // CASH_ON_HAND, BANK
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('remarks', 5000)->nullable();
            $table->integer('added_by');
            
            $table->timestamps();

            // Indexes
            $table->index('sender_id');
            $table->index('recipient_id');
            $table->index('category_id');
            $table->index('added_by');
            $table->index('bank_account_id');
            
            // Foreign key to bank_details
            $table->foreign('bank_account_id')->references('bank_id')->on('bank_details')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_txn_details');
    }
};