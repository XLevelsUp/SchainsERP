<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HeadEmployeeMapping;
use App\Models\UserDetail;

class HeadEmployeeMappingSeeder extends Seeder
{
    public function run(): void
    {
        $head     = UserDetail::where('user_name', 'head_admin')->first();
        $employee = UserDetail::where('user_name', 'employee_one')->first();

        if ($head && $employee) {
            HeadEmployeeMapping::firstOrCreate(
                ['head_id' => $head->user_id, 'employee_id' => $employee->user_id],
                ['is_active' => true]
            );
        }
    }
}
