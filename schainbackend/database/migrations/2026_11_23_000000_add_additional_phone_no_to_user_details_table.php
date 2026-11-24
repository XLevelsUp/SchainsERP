<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy SALARY user form's "Additional Phone No" (2026-11-23).
 *
 * Optional; when set it follows the phone rule (exactly 10 digits) and no two
 * users may share it. UserDetailController also keeps it apart from every
 * user's main phone_no, which the unique index can't express. Existing rows
 * start empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->string('additional_phone_no', 15)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropUnique(['additional_phone_no']);
            $table->dropColumn('additional_phone_no');
        });
    }
};
