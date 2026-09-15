<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Metal',
            'GMS Metal',
            'Chain',
            'Bangles',
            'Ring',
            'Necklace',
            'Earrings',
        ];

        foreach ($items as $name) {
            // Skip entirely if already exists — no insert attempted, no PK conflict possible
            $exists = DB::table('items')->where('item_name', $name)->exists();
            if ($exists) {
                continue;
            }

            try {
                DB::table('items')->insert([
                    'item_name'    => $name,
                    'is_active'    => true,
                    'default_touch'=> 91.6,
                    'item_touch'   => 91.6,
                ]);
            } catch (\Exception $e) {
                // If PK conflict due to sequence issue, reset sequence and retry
                if (str_contains($e->getMessage(), 'duplicate key')) {
                    DB::statement("SELECT setval('items_item_id_seq', (SELECT MAX(item_id) FROM items) + 1)");
                    DB::table('items')->insert([
                        'item_name'    => $name,
                        'is_active'    => true,
                        'default_touch'=> 91.6,
                        'item_touch'   => 91.6,
                    ]);
                }
            }
        }
    }
}
