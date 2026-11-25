<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy EMPLOYEE user form's two fields user_details didn't have
 * (2026-11-25):
 *
 *  - alloy_value: "Alloy Value", a number up to 3 decimals, 0 or more,
 *    default 0. Stored and shown only: no calculation uses it yet.
 *  - is_outside_vendor: "Is Outside Vendor" (Yes/No, default No), describing
 *    the employee. Separate from the is_outside_need_to_shown permission.
 *
 * Existing users get the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->decimal('alloy_value', 12, 3)->default(0);
            $table->boolean('is_outside_vendor')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn(['alloy_value', 'is_outside_vendor']);
        });
    }
};
