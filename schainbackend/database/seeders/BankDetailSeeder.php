<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BankDetail;

class BankDetailSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['account_name' => 'State Bank of India - Main', 'ledger_balance' => 0, 'is_active' => true],
            ['account_name' => 'HDFC Bank - City Branch',    'ledger_balance' => 0, 'is_active' => true],
        ];

        foreach ($banks as $bank) {
            $exists = DB::table('bank_details')->where('account_name', $bank['account_name'])->exists();
            if (!$exists) {
                BankDetail::create($bank);
            }
        }
    }
}
