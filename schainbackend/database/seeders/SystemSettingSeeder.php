<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Default system settings for a fresh database (BACKEND_TODO §2). Existing
 * databases get the same rows from the 2026_11_12_000000 migration.
 *
 * Only the item named "Metal" opens the metal picker and is Live Metal
 * Balance's default (agreed 2026-11-12). Found by name, because ids differ
 * between databases. A key that already holds a non-empty value is kept.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $metalIds = Item::query()
            ->whereRaw('LOWER(TRIM(item_name)) = ?', ['metal'])
            ->orderBy('item_id')
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($metalIds)) {
            $this->command?->warn('No item named "Metal" yet, so the metal item settings were not seeded.');
            return;
        }

        $defaults = [
            'metal_popup_items' => 'Items that open the metal lot picker on stock entry screens',
            'live_metal_report_items' => 'Default item(s) for the Live Metal Balance report',
        ];

        foreach ($defaults as $key => $description) {
            $existing = SystemSetting::where('setting_key', $key)->first();
            if ($existing && !empty($existing->setting_value)) {
                continue;
            }

            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $metalIds, 'description' => $description]
            );
        }
    }
}
