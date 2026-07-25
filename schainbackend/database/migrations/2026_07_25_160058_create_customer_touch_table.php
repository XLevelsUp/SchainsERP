<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_touch', function (Blueprint $table) {

            $table->bigIncrements('item_id');

            $table->string('item_name', 150);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamp('added_at')
                ->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_touch');
    }
};