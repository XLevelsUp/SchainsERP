<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BankDetail;

class BankDetailSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['bank_name' => 'State Bank of India', 'account_no' => '1234567890', 'ifsc_code' => 'SBIN0001234', 'branch' => 'Main Branch', 'is_active' => true],
            ['bank_name' => 'HDFC Bank',           'account_no' => '9876543210', 'ifsc_code' => 'HDFC0009876', 'branch' => 'City Branch',  'is_active' => true],
        ];

        foreach ($banks as $bank) {
            BankDetail::firstOrCreate(
                ['account_no' => $bank['account_no']],
                $bank
            );
        }
    }
}
