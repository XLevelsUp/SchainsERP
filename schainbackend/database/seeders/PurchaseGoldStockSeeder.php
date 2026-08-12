<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Carbon\Carbon;

class PurchaseGoldStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have some users to act as HEAD and EMPLOYEE
        $headId = 1; // Assuming 1 is HEAD
        $employeeId = 2; // Assuming 2 is Cash User / Employee

        $now = Carbon::now();

        // 1. Create a dummy PURCHASE_GOLD transaction (Inward to HEAD)
        StockDetails::create([
            'item_id' => 1, // Usually 1 is standard 22K Gold
            'given_by' => $employeeId,
            'given_to' => $headId,
            'type' => 'NORMAL',
            'entry_type' => 'NORMAL',
            'stock_type' => 'IN',
            'grams' => 10.0000,
            'touch' => 91.6000,
            'purity' => 9.1600,
            'balance' => 0.0000,
            'remarks' => 'PURCHASE_GOLD', // <--- This is the key the API filters by
            'is_freezed' => false,
            'is_completed' => false,
            'is_receiver_completed' => false,
            'is_hided' => false,
            'added_by' => $headId,
            // Opening and Closing balances for audit
            'given_by_item_grams_op' => 0.0000,
            'given_by_item_purity_op' => 0.0000,
            'given_by_item_grams_cb' => 0.0000,
            'given_by_item_purity_cb' => 0.0000,
            'given_to_item_grams_op' => 50.0000,
            'given_to_item_purity_op' => 45.8000,
            'given_to_item_grams_cb' => 60.0000,
            'given_to_item_purity_cb' => 54.9600,
            'added_at' => $now->subDays(1),
        ]);

        // 2. Create another PURCHASE_GOLD transaction
        StockDetails::create([
            'item_id' => 1,
            'given_by' => $employeeId,
            'given_to' => $headId,
            'type' => 'NORMAL',
            'entry_type' => 'NORMAL',
            'stock_type' => 'IN',
            'grams' => 25.5000,
            'touch' => 91.6000,
            'purity' => 23.3580,
            'balance' => 0.0000,
            'remarks' => 'PURCHASE_GOLD', 
            'is_freezed' => false,
            'is_completed' => false,
            'is_receiver_completed' => false,
            'is_hided' => false,
            'added_by' => $headId,
            'given_by_item_grams_op' => 0.0000,
            'given_by_item_purity_op' => 0.0000,
            'given_by_item_grams_cb' => 0.0000,
            'given_by_item_purity_cb' => 0.0000,
            'given_to_item_grams_op' => 60.0000,
            'given_to_item_purity_op' => 54.9600,
            'given_to_item_grams_cb' => 85.5000,
            'given_to_item_purity_cb' => 78.3180,
            'added_at' => $now,
        ]);

        $this->command->info('Successfully seeded PURCHASE_GOLD stock history data.');
    }
}
