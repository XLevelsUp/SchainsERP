<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\SystemSetting;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACKEND_TODO §2: the metal picker and Live Metal Balance need their
 * settings rows, resolved by item name.
 */
class MetalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $name): Item
    {
        return Item::create([
            'item_name' => $name,
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => false,
            'is_no_barcode' => true,
        ]);
    }

    public function test_seeder_maps_only_the_item_named_metal(): void
    {
        $this->item('Gold Ring');
        $metal = $this->item('Metal');
        $this->item('GMS Metal');

        $this->seed(SystemSettingSeeder::class);

        $this->assertSame([$metal->item_id], SystemSetting::get('metal_popup_items'));
        $this->assertSame([$metal->item_id], SystemSetting::get('live_metal_report_items'));
    }

    public function test_seeder_keeps_a_value_that_is_already_configured(): void
    {
        $this->item('Metal');
        $gms = $this->item('GMS Metal');
        SystemSetting::create(['setting_key' => 'metal_popup_items', 'setting_value' => [$gms->item_id]]);

        $this->seed(SystemSettingSeeder::class);

        $this->assertSame([$gms->item_id], SystemSetting::get('metal_popup_items'));
    }

    public function test_migration_fills_settings_on_an_existing_database(): void
    {
        $metal = $this->item('Metal');
        SystemSetting::query()->delete();

        $migration = require database_path('migrations/2026_11_12_000000_seed_metal_item_settings.php');
        $migration->up();

        $this->assertSame([$metal->item_id], SystemSetting::get('metal_popup_items'));
        $this->assertSame([$metal->item_id], SystemSetting::get('live_metal_report_items'));
    }
}
