<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The company name both side menus show as a group heading (2026-11-24,
 * "NIHAA JEWELS"). A system setting, so heads can change it in the app's
 * System Settings without a code change. Left alone if it already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('system_settings')->where('setting_key', 'company_name')->exists()) {
            return;
        }

        DB::table('system_settings')->insert([
            'setting_key' => 'company_name',
            // setting_value is JSON (SystemSetting casts it): a JSON string.
            'setting_value' => json_encode('NIHAA JEWELS'),
            'description' => 'Company name shown in the side menus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'company_name')->delete();
    }
};
