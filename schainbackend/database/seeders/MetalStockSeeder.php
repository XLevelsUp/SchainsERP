<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Carbon\Carbon;

class MetalStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Find item ID 2 (which we updated to Metal)
        $metalItem = Item::find(2);

        // Find the first admin and the first customer in the DB dynamically
        $adminUser = UserDetail::where('category_name', 'ADMIN')->first();
        $customerUser = UserDetail::where('category_name', 'RETAILER')->first();

        if (!$adminUser || !$customerUser) {
            echo "Error: Need at least one ADMIN and one CUSTOMER in user_details table.\n";
            return;
        }

        // Create dummy StockDetails (Metal stock) with balance > 0 for the customer
        StockDetails::create([
            'date' => Carbon::now(),
            'stock_type' => 'IN',
            'type' => 'NORMAL',
            'entry_type' => 'NORMAL',
            'given_by' => $adminUser->user_id,
            'given_to' => $customerUser->user_id, // Assigned to the user we are testing with
            'item_id' => 2,
            'to_item_id' => 2,
            'grams' => 500,
            'touch' => 91.6,
            'balance' => 500, // Important: must be > 0
            'purity' => 458, // 500 * 0.916
            'is_hided' => false, // Important: must be 0
            'remarks' => 'Initial Metal Stock',
            'added_at' => Carbon::now(),
            'added_by' => $adminUser->user_id
        ]);

        StockDetails::create([
            'date' => Carbon::now(),
            'stock_type' => 'IN',
            'type' => 'NORMAL',
            'entry_type' => 'NORMAL',
            'given_by' => $adminUser->user_id,
            'given_to' => $customerUser->user_id, 
            'item_id' => 2,
            'to_item_id' => 2,
            'grams' => 200,
            'touch' => 99.9,
            'balance' => 200,
            'purity' => 199.8,
            'is_hided' => false, 
            'remarks' => 'Second Metal Stock',
            'added_at' => Carbon::now(),
            'added_by' => $adminUser->user_id
        ]);
        
        echo "MetalStockSeeder successfully ran! Metal stock seeded for User ID: " . $customerUser->user_id . "\n";
    }
}