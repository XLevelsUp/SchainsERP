<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin console accounts (App\Models\Admin). Separate from user_details:
 * an admin is not a head, holds no balances and never appears in the app's
 * pickers. Admins create users and assign them to heads.
 *
 * special_password_hash is a second secret, asked for again on sensitive
 * changes (assigning heads, deleting a user). See RequireSpecialPassword.
 *
 * Accounts are created with `php artisan admin:create`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('user_name', 50)->unique();
            $table->string('password_hash');
            $table->string('special_password_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('special_password_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
