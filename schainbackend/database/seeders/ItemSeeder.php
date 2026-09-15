<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['item_name' => 'Metal',      'item_type' => 'NORMAL', 'is_active' => true],
            ['item_name' => 'GMS Metal',  'item_type' => 'GMS',    'is_active' => true],
            ['item_name' => 'Chain',      'item_type' => 'NORMAL', 'is_active' => true],
            ['item_name' => 'Bangles',    'item_type' => 'NORMAL', 'is_active' => true],
            ['item_name' => 'Ring',       'item_type' => 'NORMAL', 'is_active' => true],
            ['item_name' => 'Necklace',   'item_type' => 'NORMAL', 'is_active' => true],
            ['item_name' => 'Earrings',   'item_type' => 'NORMAL', 'is_active' => true],
        ];

        foreach ($items as $item) {
            Item::firstOrCreate(
                ['item_name' => $item['item_name']],
                $item
            );
        }
    }
}
