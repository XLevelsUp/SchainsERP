<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opening balance updates (2026-11-24, the legacy "Create new Opening
 * Balance Update" modal on the user form): every manual change an admin
 * makes to a user's balances, with the figure before and after.
 *
 * field:
 *  - ITEM_GRAMS / ITEM_PURITY: an item's totals (users_items_mappings);
 *    item_id says which. The user's grams / purity grand total moves by the
 *    same value and gets its own GRAMS / PURITY row (agreed with the user).
 *  - GRAMS / PURITY: user_details.grams_grand_total / purity_grand_total.
 *  - CASH / RTGS: user_details.rak_cash_balance / rak_rtgs_balance.
 *
 * closing_balance = previous_balance + value_update (a negative value
 * reduces it). Written by App\Services\OpeningBalanceService only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('item_id')->nullable();
            $table->string('field', 20);
            $table->string('remarks', 500)->nullable();
            $table->decimal('previous_balance', 18, 4);
            $table->decimal('value_update', 18, 4);
            $table->decimal('closing_balance', 18, 4);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('user_details')->cascadeOnDelete();
            $table->foreign('item_id')->references('item_id')->on('items')->nullOnDelete();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_updates');
    }
};
