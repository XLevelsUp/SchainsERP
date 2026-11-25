<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\HeadEmployeeMapping;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The full form a role without its own profile gets (e.g. PURCHASE) has no
 * role-only sections (fixed 2026-11-25): no Head users (HEAD only) and no
 * Additional phone (SALARY only). Sent anyway, they're dropped: an employee
 * is never saved as someone's head.
 */
class AdminFullFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    public function test_the_full_form_keeps_head_lists_but_never_gets_head_users_or_an_additional_phone(): void
    {
        $headRole = Role::create(['role' => 'HEAD']);
        // A role without its own profile (EMPLOYEE has one since 2026-11-25).
        $employeeRole = Role::create(['role' => 'PURCHASE']);
        $head = UserDetail::create([
            'name' => 'New User 001', 'user_name' => 'user001', 'password_hash' => Hash::make('pass-1234'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => '9600000001', 'proff' => '-',
            'role_id' => $headRole->id, 'mailing_name' => 'user001', 'category_name' => 'BOTH', 'system_id' => 'SYS_user001',
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
        $this->signInToAdminConsoleAs($this->makeAdmin());

        $this->postJson('/api/v1/admin/user-details', [
            'name' => 'employee 1', 'user_name' => 'employee1', 'password' => 'secret-12', 'phone_no' => '9456788388',
            'role_id' => $employeeRole->id, 'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => 'employee1', 'category_name' => 'GRAMS', 'system_id' => 'SYS_employee1',
            'head_mappings' => [['head_id' => $head->user_id]],         // kept: the employee's Head lists
            'head_user_mappings' => [['employee_id' => $head->user_id]], // dropped: HEAD only
            'additional_phone_no' => '9456788399',                       // dropped: SALARY only
        ], $this->withSpecialPassword())->assertStatus(201);

        $employee = UserDetail::where('user_name', 'employee1')->firstOrFail();
        $this->assertNull($employee->additional_phone_no);
        // Linked under the head, as Head lists says…
        $this->assertTrue(HeadEmployeeMapping::where('head_id', $head->user_id)->where('employee_id', $employee->user_id)->exists());
        // …and never as anyone's head.
        $this->assertFalse(HeadEmployeeMapping::where('head_id', $employee->user_id)->exists());
    }
}
