<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_txn_images', function (Blueprint $table) {
            $table->id('image_id');
            $table->unsignedBigInteger('cash_txn_id');
            $table->string('image_path', 255);
            $table->timestamps();

            $table->foreign('cash_txn_id')
                  ->references('txn_id')
                  ->on('cash_txn_details')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_txn_images');
    }
};