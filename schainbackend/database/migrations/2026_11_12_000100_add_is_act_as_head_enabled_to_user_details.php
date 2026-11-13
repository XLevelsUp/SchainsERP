<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Can act as head" permission (agreed 2026-11-12). A user with this flag can
 * enter stock and cash values for any head and open that head's history,
 * lots and stock summary, but never sees balances. Heads (role HEAD) don't
 * need it. See App\Http\Controllers\Concerns\ScopesRecordAccess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->boolean('is_act_as_head_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn('is_act_as_head_enabled');
        });
    }
};
