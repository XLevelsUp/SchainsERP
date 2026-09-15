<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserDetailSeeder::class,
            ItemSeeder::class,
            CashCategorySeeder::class,
            BankDetailSeeder::class,
            HeadEmployeeMappingSeeder::class,
        ]);
    }
}
