<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashCategory;

class CashCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Food/Lunch', 'category_type' => 'EXPENSE'],
            ['category_name' => 'Transport', 'category_type' => 'EXPENSE'],
            ['category_name' => 'Salary Advance', 'category_type' => 'EXPENSE'],
            ['category_name' => 'Miscellaneous Income', 'category_type' => 'INCOME'],
            ['category_name' => 'System Auto Entry', 'category_type' => 'AUTO_ENTRY'],
        ];

        foreach ($categories as $category) {
            CashCategory::firstOrCreate(
                ['category_name' => $category['category_name']],
                [
                    'category_type' => $category['category_type'],
                    'is_active' => true,
                    'added_by' => 1
                ]
            );
        }
    }
}
