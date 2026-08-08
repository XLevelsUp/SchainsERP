<?php

namespace Database\Seeders;

use App\Models\UserDetail;
use App\Models\Item;
use App\Models\UsersItemsMapping;
use App\Models\StockDetails;
use App\Models\GmsHistory;
use App\Models\NumericWastage;
use App\Models\CashTxnDetail;
use App\Models\BillingEntry;
use App\Models\OverAllBill;
use App\Models\WastageDetails;
use App\Models\BankDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure clean transaction and master state for IDs 1 and 2
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE stock_details, gms_history, numeric_wastages, cash_txn_details, cash_txn_images, bank_details, billing_entries, over_all_bills, wastage_details RESTART IDENTITY CASCADE;');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF;');
            StockDetails::truncate();
            GmsHistory::truncate();
            NumericWastage::truncate();
            CashTxnDetail::truncate();
            BillingEntry::truncate();
            OverAllBill::truncate();
            WastageDetails::truncate();
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        UsersItemsMapping::whereIn('user_id', [1, 2])->delete();
        UserDetail::whereIn('user_id', [1, 2])->delete();
        Item::whereIn('item_id', [1, 2])->delete();
        WastageDetails::where('waste_id', 1)->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('user_details_user_id_seq', COALESCE((SELECT MAX(user_id)+1 FROM user_details), 1), false);");
            DB::statement("SELECT setval('items_item_id_seq', COALESCE((SELECT MAX(item_id)+1 FROM items), 1), false);");
            DB::statement("SELECT setval('users_items_mappings_id_seq', COALESCE((SELECT MAX(id)+1 FROM users_items_mappings), 1), false);");
            DB::statement("SELECT setval('bank_details_bank_id_seq', COALESCE((SELECT MAX(bank_id)+1 FROM bank_details), 1), false);");
        }

        // 2. Create Items
        Item::create([
            'item_id' => 1,
            'item_name' => 'Gold Ring',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);

        Item::create([
            'item_id' => 2,
            'item_name' => 'Gold Chain',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);

        Item::create([
            'item_id' => 3,
            'item_name' => 'Gold',
            'is_active' => true,
            'default_touch' => 100.00,
            'item_touch' => 100.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => false,
            'is_no_barcode' => true,
        ]);

        // 3. Create Users
        UserDetail::create([
            'user_id' => 1,
            'name' => 'Head Admin',
            'user_name' => 'head_admin',
            'password_hash' => '$2y$12$otbYsBjIT00Tj2sJuultO.4q3PgVb9Rrz3aW.eMLlxrqBwFYnAOUK',
            'address' => 'Office',
            'signature' => 'HA',
            'code' => 'HA001',
            'phone_no' => '9876543201',
            'remarks' => 'Head admin user',
            'proff' => 'Head',
            'role_id' => 1,
            'mailing_name' => 'Head Admin Mailing',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_HEAD',
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true,
            'is_delete' => false,
            'is_billable' => true,
            'grams_grand_total' => 1000.0000,
            'purity_grand_total' => 920.0000,
            'rak_cash_balance' => 50000.0000,
        ]);

        UserDetail::create([
            'user_id' => 2,
            'name' => 'Employee One',
            'user_name' => 'employee_one',
            'password_hash' => '$2y$12$otbYsBjIT00Tj2sJuultO.4q3PgVb9Rrz3aW.eMLlxrqBwFYnAOUK',
            'address' => 'Branch',
            'signature' => 'E1',
            'code' => 'E001',
            'phone_no' => '9876543202',
            'remarks' => 'Employee user',
            'proff' => 'Employee',
            'role_id' => 2,
            'mailing_name' => 'Employee One Mailing',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_EMP1',
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true,
            'is_delete' => false,
            'is_billable' => true,
            'grams_grand_total' => 0.0000,
            'purity_grand_total' => 0.0000,
            'rak_cash_balance' => 1000.0000,
        ]);

        // 4. Create User Items Mappings
        UsersItemsMapping::create([
            'user_id' => 1,
            'item_id' => 1,
            'item_grams_total' => 500.0000,
            'item_purity_total' => 460.0000,
            'is_primary' => 1,
        ]);

        UsersItemsMapping::create([
            'user_id' => 1,
            'item_id' => 2,
            'item_grams_total' => 500.0000,
            'item_purity_total' => 460.0000,
            'is_primary' => 1,
        ]);

        UsersItemsMapping::create([
            'user_id' => 1,
            'item_id' => 3,
            'item_grams_total' => 0.0000,
            'item_purity_total' => 0.0000,
            'is_primary' => 0,
        ]);

        UsersItemsMapping::create([
            'user_id' => 2,
            'item_id' => 1,
            'item_grams_total' => 0.0000,
            'item_purity_total' => 0.0000,
            'is_primary' => 1,
        ]);

        UsersItemsMapping::create([
            'user_id' => 2,
            'item_id' => 2,
            'item_grams_total' => 0.0000,
            'item_purity_total' => 0.0000,
            'is_primary' => 1,
        ]);

        UsersItemsMapping::create([
            'user_id' => 2,
            'item_id' => 3,
            'item_grams_total' => 0.0000,
            'item_purity_total' => 0.0000,
            'is_primary' => 0,
        ]);

        // WastageDetails::create([
        //     'waste_id' => 1,
        //     'waste_name' => 'RUVIE DYE',
        //     'waste_value' => '0.1800',
        // ]);

        BankDetail::create([
            'bank_id' => 1,
            'account_name' => 'HDFC Bank Ltd',
            'ledger_balance' => 10000.00,
            'is_active' => true,
        ]);
        
        BankDetail::create([
            'bank_id' => 2,
            'account_name' => 'ICICI Bank Ltd',
            'ledger_balance' => 15000.00,
            'is_active' => true,
        ]);
    }
}

