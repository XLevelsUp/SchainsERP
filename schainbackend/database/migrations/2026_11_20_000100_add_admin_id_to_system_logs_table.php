<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin console actions are audited too (sign-in, users created or deleted,
 * heads assigned). The actor is an admin, not a user_details row, so it
 * gets its own column; user_id stays null on those rows. Nullable, so every
 * existing row is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });
    }
};
