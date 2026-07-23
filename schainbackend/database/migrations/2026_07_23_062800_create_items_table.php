<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {

            $table->increments('item_id');

            $table->string('item_name', 255);

            $table->boolean('is_active')
                ->default(true);

            $table->float('default_touch')
                ->default(92);

            $table->float('item_touch')
                ->default(0);

            $table->timestamp('added_at')
                ->useCurrent();

            $table->boolean('is_need_fitem_shown')
                ->default(false);

            $table->double('mtouch')
                ->nullable();

            $table->boolean('is_barcode')
                ->default(false);

            $table->boolean('is_no_barcode')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};