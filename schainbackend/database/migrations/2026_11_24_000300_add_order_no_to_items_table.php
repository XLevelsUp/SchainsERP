<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy Items page's "Orders No" (2026-11-24): the order items appear
 * in, in every item dropdown and list (GET /items sorts by it).
 *
 * A whole number of 1 or more, unique; a new item gets the next free number
 * (ItemController). Existing items are numbered oldest first here, so the
 * column can be required; the order is then set on the Items page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('order_no')->nullable();
        });

        DB::table('items')->orderBy('item_id')->pluck('item_id')->each(
            fn ($id, $index) => DB::table('items')->where('item_id', $id)->update(['order_no' => $index + 1])
        );

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('order_no')->nullable(false)->change();
            $table->unique('order_no');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['order_no']);
            $table->dropColumn('order_no');
        });
    }
};
