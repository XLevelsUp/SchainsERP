<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashHeadEmployeeMapping;
use App\Models\HeadEmployeeMapping;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The EMPLOYEE user form (2026-11-25, the legacy employee form): Head lists,
 * items, balances, image, joining date, Alloy Value and Is Outside Vendor.
 * Employees don't sign in, and the form has no permission switches; new
 * employees start on BOTH, GOLD and today's joining date (form defaults).
 */
class AdminEmployeeFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $employeeRole;
    protected UserDetail $head;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->head = $this->makeUser('user001', '9600000001', $headRole);
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, string $phone, Role $role, array $extra = []): UserDetail
    {
        return UserDetail::create(array_merge([
            'name' => $userName, 'user_name' => $userName, 'password_hash' => Hash::make('old-pass'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $role->id, 'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ], $extra));
    }

    public function test_the_console_reads_the_employee_profile(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $employee = $this->getJson('/api/v1/admin/user-form-profiles')->assertOk()
            ->json('data.by_role_id.' . $this->employeeRole->id);

        $this->assertFalse($employee['is_default']);
        $this->assertFalse($employee['signs_in']);
        $this->assertSame('BOTH', $employee['default_category']);
        $this->assertSame('GOLD', $employee['default_primary_item']);
        $this->assertTrue($employee['joining_date_defaults_to_today']);
        $this->assertSame(['employee', 'heads', 'items', 'balances', 'image', 'joining_date'], $employee['sections']);
        $this->assertSame([], $employee['flags']);
    }

    public function test_an_employee_is_created_with_the_legacy_fields_only(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/user-details', [
            'user_name' => 'employee1', 'phone_no' => '9456788388', 'role_id' => $this->employeeRole->id,
            'category_name' => 'BOTH', 'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => '', 'system_id' => 'SYS_employee1', 'joining_date' => '2026-11-25',
            'name' => 'Ignored', 'password' => 'known-pass',                    // dropped: no sign-in
            'head_mappings' => [['head_id' => $this->head->user_id]],
            'cash_head_mappings' => [['head_id' => $this->head->user_id]],     // dropped: not on the form
            'alloy_value' => '12.345',
            'is_outside_vendor' => true,
            'is_act_as_head_enabled' => true,                                   // dropped: no permissions
            'customer_commants' => 'dropped',
            'aadhar_number' => 'dropped',
        ], $this->withSpecialPassword())->assertStatus(201);

        $employee = UserDetail::where('user_name', 'employee1')->firstOrFail();
        $this->assertSame('employee1', $employee->name);
        $this->assertFalse(Hash::check('known-pass', $employee->password_hash));
        $this->assertEquals(12.345, (float) $employee->alloy_value);
        $this->assertTrue($employee->is_outside_vendor);
        $this->assertFalse((bool) $employee->is_act_as_head_enabled);
        $this->assertNull($employee->customer_commants);
        $this->assertNull($employee->aadhar_number);
        $this->assertTrue(HeadEmployeeMapping::where('head_id', $this->head->user_id)->where('employee_id', $employee->user_id)->exists());
        $this->assertFalse(CashHeadEmployeeMapping::where('employee_id', $employee->user_id)->exists());

        $this->getJson('/api/v1/admin/user-details/' . $employee->user_id)->assertOk()
            ->assertJsonPath('data.user.is_outside_vendor', true);
    }

    public function test_alloy_value_is_a_number_of_zero_or_more_with_three_decimals(): void
    {
        $employee = $this->makeUser('employee2', '9456788390', $this->employeeRole, ['is_act_as_head_enabled' => true]);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $employee->user_id;
        $message = 'Alloy value must be a number of 0 or more with up to 3 decimals.';

        foreach (['-1', '1.2345', 'abc'] as $bad) {
            $this->putJson($url, ['alloy_value' => $bad])->assertStatus(422)->assertJsonPath('errors.alloy_value.0', $message);
        }

        // Blank is 0; an edit leaves the saved permissions alone.
        $this->putJson($url, ['alloy_value' => '', 'is_outside_vendor' => false, 'is_act_as_head_enabled' => false])->assertOk();
        $employee->refresh();
        $this->assertEquals(0, (float) $employee->alloy_value);
        $this->assertFalse($employee->is_outside_vendor);
        $this->assertTrue((bool) $employee->is_act_as_head_enabled);
    }
}
