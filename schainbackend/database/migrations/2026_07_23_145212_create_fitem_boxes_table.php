<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fitem_boxes', function (Blueprint $table) {
            $table->id('box_id');

            $table->string('box_name', 100);

            $table->unsignedBigInteger('item_id');

            $table->boolean('is_active')
                ->default(false);

            $table->unsignedBigInteger('added_by');

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamp('added_at')
                ->useCurrent();

            $table->timestamp('updated_at')
                ->useCurrent();

            // Optional relationship with items table
            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitem_boxes');
    }
};