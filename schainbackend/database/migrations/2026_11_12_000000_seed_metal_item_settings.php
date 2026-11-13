<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BACKEND_TODO §2: `system_settings` was never seeded, so the metal picker
 * (`metal_popup_items`) rejected every item with a 400 and Live Metal Balance
 * (`live_metal_report_items`) had no default item.
 *
 * Agreed 2026-11-12: only the item named "Metal" opens the picker, and it is
 * also Live Metal Balance's default. Ids differ between databases, so the item
 * is found by name. A key that already exists is never overwritten. On a fresh
 * database (no items yet) nothing is written here; SystemSettingSeeder fills
 * it in after ItemSeeder.
 */
return new class extends Migration
{
    private const KEYS = [
        'metal_popup_items' => 'Items that open the metal lot picker on stock entry screens',
        'live_metal_report_items' => 'Default item(s) for the Live Metal Balance report',
    ];

    public function up(): void
    {
        $metalIds = DB::table('items')
            ->whereRaw('LOWER(TRIM(item_name)) = ?', ['metal'])
            ->orderBy('item_id')
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($metalIds)) {
            return;
        }

        foreach (self::KEYS as $key => $description) {
            if (DB::table('system_settings')->where('setting_key', $key)->exists()) {
                continue;
            }

            DB::table('system_settings')->insert([
                'setting_key' => $key,
                'setting_value' => json_encode($metalIds),
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', array_keys(self::KEYS))->delete();
    }
};
