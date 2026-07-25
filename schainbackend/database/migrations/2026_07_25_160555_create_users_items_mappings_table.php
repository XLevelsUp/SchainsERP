<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_items_mappings', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->unsignedBigInteger('item_id');

            $table->unsignedBigInteger('user_id');

            $table->timestamp('added_at')
                ->useCurrent();

            $table->string('item_grams_total', 255)
                ->default('0');

            $table->string('item_purity_total', 255)
                ->default('0');

            $table->double('stone_cost_grand_total')
                ->nullable();

            $table->double('beads_cost_grand_total')
                ->nullable();

            $table->double('beads_stone_cost_grand_total')
                ->nullable();

            $table->dateTime('last_txn_date')
                ->nullable();

            $table->integer('is_primary');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('user_details')
                ->onDelete('cascade');

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->onDelete('cascade');

            $table->index(
                'user_id',
                'users_items_mappings_user_id_idx'
            );

            $table->index(
                'item_id',
                'users_items_mappings_item_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_items_mappings');
    }
};