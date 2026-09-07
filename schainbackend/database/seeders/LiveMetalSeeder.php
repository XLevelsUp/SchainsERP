<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LiveMetalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Add a valid Gold (item_id 2) stock for User 1 (Admin)
        DB::table('stock_details')->insert([
            'given_to' => 1,
            'given_by' => 2,
            'item_id' => 2, // Gold
            'entry_type' => 'NORMAL',
            'type' => 'NORMAL',
            'stock_type' => 'IN',
            'remarks' => 'Live Metal Testing',
            'grams' => 150.000,
            'touch' => 91.6,
            'purity' => 137.400,
            'balance' => 150.000,

            'added_by' => 1,
            'added_by' => 1,
            'added_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        
        // Add another conversion type stock for User 1
        DB::table('stock_details')->insert([
            'given_to' => 1,
            'given_by' => 2,
            'item_id' => 2,
            'to_item_id' => 2, // Gold
            'entry_type' => 'EMPTOHEAD',
            'type' => 'NORMAL', // Since it's not ITEMCHANGE/CONVERSION, IN/OUT applies
            'stock_type' => 'IN',
            'remarks' => 'Live Metal Testing 2',
            'grams' => 50.000,
            'touch' => 100.0,
            'purity' => 50.000,
            'balance' => 50.000,

            'added_by' => 1,
            'added_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
