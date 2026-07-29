<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_txn_images', function (Blueprint $table) {

            $table->bigIncrements('image_id');

            $table->unsignedBigInteger('txn_id');

            $table->string('image_url', 150);

            $table->timestamp('added_at')
                  ->useCurrent();

            $table->index(
                'txn_id',
                'cash_txn_images_FK1_idx'
            );

            $table->foreign('txn_id')
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