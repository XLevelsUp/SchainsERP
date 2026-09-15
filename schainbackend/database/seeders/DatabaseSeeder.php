<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reset PostgreSQL sequences for all relevant tables to prevent PK conflicts
        // This is safe — sequences are only pushed forward, never reset to 0
        $sequences = [
            'roles'                   => ['table' => 'roles',                   'seq' => 'roles_id_seq',                    'pk' => 'id'],
            'user_details'            => ['table' => 'user_details',            'seq' => 'user_details_user_id_seq',         'pk' => 'user_id'],
            'items'                   => ['table' => 'items',                   'seq' => 'items_item_id_seq',                'pk' => 'item_id'],
            'cash_categories'         => ['table' => 'cash_categories',         'seq' => 'cash_categories_id_seq',           'pk' => 'id'],
            'bank_details'            => ['table' => 'bank_details',            'seq' => 'bank_details_bank_id_seq',         'pk' => 'bank_id'],
            'head_employee_mappings'  => ['table' => 'head_employee_mappings',  'seq' => 'head_employee_mappings_id_seq',    'pk' => 'id'],
        ];

        foreach ($sequences as $cfg) {
            try {
                DB::statement(
                    "SELECT setval('{$cfg['seq']}', (SELECT COALESCE(MAX({$cfg['pk']}), 0) + 1 FROM {$cfg['table']}))"
                );
            } catch (\Exception $e) {
                // Sequence may not exist on the server — skip silently
            }
        }

        $this->call([
            RoleSeeder::class,
            UserDetailSeeder::class,
            ItemSeeder::class,
            CashCategorySeeder::class,
            BankDetailSeeder::class,
            HeadEmployeeMappingSeeder::class,
            MassTestDataSeeder::class,
        ]);
    }
}
