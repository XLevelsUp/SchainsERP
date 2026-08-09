<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'role' => 'CUSTOMER', 'added_at' => now(), 'touch' => 0],
            ['id' => 2, 'role' => 'EMPLOYEE', 'added_at' => now(), 'touch' => 0],
            ['id' => 3, 'role' => 'HEAD', 'added_at' => now(), 'touch' => 0],
            ['id' => 4, 'role' => 'PURCHASE', 'added_at' => now(), 'touch' => 0],
            ['id' => 5, 'role' => 'SALARY', 'added_at' => now(), 'touch' => 0],
        ];

        foreach ($roles as $role) {
            \App\Models\Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
}
