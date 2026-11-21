<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the legacy HEAD user form has and user_details didn't (2026-11-21):
 *
 *  - aadhar_number: the form's "Aadhar Number" (proff is free-text proof
 *    notes, not this);
 *  - aadhar_back_image: "Aadhar Front & Back Images" — aadhar_image is the
 *    front;
 *  - three permissions the app now checks on sign-in: Item Change and Item
 *    Conversion on the Stock screen, and the Phone Book.
 *
 * The permissions default off and nothing is backfilled (agreed 2026-11-21):
 * every user starts from what's saved, and an admin switches features on per
 * user in the admin console.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->string('aadhar_number', 20)->nullable();
            $table->string('aadhar_back_image', 255)->nullable();
            $table->boolean('is_item_change_need_shown')->default(false);
            $table->boolean('is_item_conversion_need_shown')->default(false);
            $table->boolean('is_phone_book_need_shown')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn([
                'aadhar_number',
                'aadhar_back_image',
                'is_item_change_need_shown',
                'is_item_conversion_need_shown',
                'is_phone_book_need_shown',
            ]);
        });
    }
};
