<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The phone book (2026-11-24, the legacy Phone Books screen): its own list
 * of contacts (name, phone, role, active), managed in the admin console and
 * read in the app.
 *
 * Until now GET /phone-book listed user_details. Agreed with the user: the
 * phone book is a separate list, every existing user is copied in once here,
 * and each user the admin console creates afterwards is added
 * (App\Support\PhoneBookSync). Editing or deleting a contact never touches
 * the user; user_id only links a contact to the user it came from (for the
 * picture) and is cleared if that user is deleted.
 *
 * phone_no is unique. New and edited contacts need exactly 10 digits
 * (PhoneBookController); copied numbers are kept as they are and must be
 * corrected on their next save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_book', function (Blueprint $table) {
            $table->id();
            $table->string('name', 55);
            $table->string('phone_no', 15)->unique();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('user_id')->references('user_id')->on('user_details')->nullOnDelete();
        });

        // The one-time copy of the existing users. user_details.role_id is
        // text; a value that isn't an existing role is left empty.
        $now = now();
        $roleIds = DB::table('roles')->pluck('id')->map(fn ($id) => (int) $id)->all();
        DB::table('user_details')
            ->where('is_delete', false)
            ->whereNotNull('phone_no')
            ->where('phone_no', '<>', '')
            ->orderBy('user_id')
            ->get(['user_id', 'name', 'phone_no', 'role_id'])
            ->each(function ($user) use ($now, $roleIds) {
                $roleId = is_numeric($user->role_id) ? (int) $user->role_id : null;
                DB::table('phone_book')->insertOrIgnore([
                    'name' => mb_substr((string) $user->name, 0, 55),
                    'phone_no' => $user->phone_no,
                    'role_id' => in_array($roleId, $roleIds, true) ? $roleId : null,
                    'is_active' => true,
                    'user_id' => $user->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_book');
    }
};
