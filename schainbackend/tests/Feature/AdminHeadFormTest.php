<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\HeadEmployeeMapping;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The HEAD user form (2026-11-21, the legacy head form) and the app showing
 * a signed-in user only what their permissions allow.
 */
class AdminHeadFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Role $employeeRole;
    protected Role $adminRole;
    protected UserDetail $employee;
    protected UserDetail $customer;
    protected UserDetail $otherHead;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->adminRole = Role::create(['role' => 'ADMIN']);
        $customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->employee = $this->makeUser('employee_a', $this->employeeRole->id, '9600000001');
        $this->customer = $this->makeUser('customer_a', $customerRole->id, '9600000002');
        $this->otherHead = $this->makeUser('head_z', $this->headRole->id, '9600000003');
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, int $roleId, string $phone, array $extra = []): UserDetail
    {
        return UserDetail::create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $userName)),
            'user_name' => $userName,
            'password_hash' => 'dummy',
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $roleId,
            'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ], $extra));
    }

    private function headPayload(array $extra = []): array
    {
        return array_merge([
            'name' => 'NIHAA(RAMESH)',
            'user_name' => 'nihaa_ramesh',
            'password' => 'secret123',
            'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'phone_no' => '9600000099',
            'role_id' => $this->headRole->id,
            'mailing_name' => 'NIHAA(RAMESH)',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_NIHAA_RAMESH',
        ], $extra);
    }

    private function editBase(UserDetail $user): array
    {
        return [
            'name' => $user->name,
            'user_name' => $user->user_name,
            'address' => 'Test',
            'phone_no' => $user->phone_no,
            'category_name' => 'BOTH',
        ];
    }

    public function test_a_head_is_created_with_the_head_forms_fields(): void
    {
        Storage::fake('public');
        $this->signInToAdminConsoleAs($this->admin);

        $payload = $this->headPayload([
            // Head users are HEAD users only (2026-11-23).
            'head_user_mappings' => [['employee_id' => $this->otherHead->user_id]],
            'aadhar_number' => '1234 5678 9012',
            'per_day_salary' => '650.50',
            'joining_date' => '2026-05-30',
            'report_password' => 'rpt-secret',
            'is_salary_person' => true,
            'is_item_change_need_shown' => true,
            'is_phone_book_need_shown' => true,
            'is_cash_mngmt_need_to_shown' => true,
            'customer_commants' => 'not on the HEAD form',            // dropped
            'head_mappings' => [['head_id' => $this->employee->user_id]], // dropped: HEAD has no "heads"
            'image_url' => UploadedFile::fake()->image('logo.png'),
            'aadhar_image' => UploadedFile::fake()->image('front.png'),
            'aadhar_back_image' => UploadedFile::fake()->image('back.png'),
        ]);

        // Head users are a head assignment: the special password.
        $this->post('/api/v1/admin/user-details', $payload, ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonValidationErrors(['special_password']);
        // Employees and customers can't be head users.
        $notHeads = ['head_user_mappings' => [['employee_id' => $this->employee->user_id], ['employee_id' => $this->customer->user_id]]] + $payload;
        $this->post('/api/v1/admin/user-details', $notHeads, ['Accept' => 'application/json'] + $this->withSpecialPassword())
            ->assertStatus(422)
            ->assertJsonPath('errors', [
                'head_user_mappings.0.employee_id' => ['Only HEAD users can be listed as head users.'],
                'head_user_mappings.1.employee_id' => ['Only HEAD users can be listed as head users.'],
            ]);
        $this->assertFalse(UserDetail::where('user_name', 'nihaa_ramesh')->exists());
        $this->post('/api/v1/admin/user-details', $payload, ['Accept' => 'application/json'] + $this->withSpecialPassword())
            ->assertStatus(201);

        $head = UserDetail::where('user_name', 'nihaa_ramesh')->firstOrFail();
        $this->assertSame('1234 5678 9012', $head->aadhar_number);
        $this->assertEquals(650.5, (float) $head->per_day_salary);
        $this->assertSame('2026-05-30', $head->joining_date->format('Y-m-d'));
        $this->assertTrue(Hash::check('rpt-secret', $head->report_password));
        $this->assertTrue((bool) $head->is_salary_person);
        $this->assertTrue($head->is_item_change_need_shown);
        $this->assertFalse($head->is_item_conversion_need_shown); // not sent: default off
        $this->assertTrue($head->is_phone_book_need_shown);
        $this->assertNull($head->customer_commants);
        $this->assertFalse(HeadEmployeeMapping::where('employee_id', $head->user_id)->exists());
        $this->assertSame(
            [$this->otherHead->user_id],
            HeadEmployeeMapping::where('head_id', $head->user_id)->pluck('employee_id')->map(fn ($id) => (int) $id)->all(),
        );
        foreach (['image_url', 'aadhar_image', 'aadhar_back_image'] as $field) {
            $this->assertNotNull($head->{$field});
            Storage::disk('public')->assertExists($head->{$field});
        }

        $shown = $this->getJson('/api/v1/admin/user-details/' . $head->user_id)->assertOk()
            ->assertJsonPath('data.user.has_report_password', true)
            ->assertJsonMissingPath('data.user.report_password')
            ->json('data');
        $this->assertNotNull($shown['image_url']);
        $this->assertNotNull($shown['aadhar_back_image_url']);
        $this->assertCount(1, $shown['user']['head_user_mappings']);
    }

    public function test_changing_head_users_needs_the_special_password_and_a_head_may_list_themselves(): void
    {
        $head = $this->makeUser('head_a', $this->headRole->id, '9600000010');
        // A link to an employee, made from the employee's own Head lists.
        HeadEmployeeMapping::create(['head_id' => $head->user_id, 'employee_id' => $this->employee->user_id, 'added_at' => now()]);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $head->user_id;
        $base = $this->editBase($head);
        $linked = fn () => HeadEmployeeMapping::where('head_id', $head->user_id)->pluck('employee_id')->map(fn ($id) => (int) $id)->all();

        // The form shows the head's links to other heads only.
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.user.head_user_mappings');

        // Not sent: nothing to confirm, nothing changes.
        $this->putJson($url, $base + ['aadhar_number' => '9999'])->assertOk();
        $this->assertSame('9999', $head->fresh()->aadhar_number);

        $set = $base + ['head_user_mappings' => [['employee_id' => $head->user_id], ['employee_id' => $this->otherHead->user_id]]];
        $this->putJson($url, $set)->assertStatus(422)->assertJsonValidationErrors(['special_password']);
        $this->putJson($url, $set, $this->withSpecialPassword())->assertOk();
        // The employee's link stays: Head users never touch non-heads.
        $this->assertEqualsCanonicalizing([$head->user_id, $this->otherHead->user_id, $this->employee->user_id], $linked());
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.user.head_user_mappings');

        // A customer is refused, and nothing changes.
        $errors = $this->putJson($url, $base + ['head_user_mappings' => [['employee_id' => $this->customer->user_id]]], $this->withSpecialPassword())
            ->assertStatus(422)->json('errors');
        $this->assertSame(['Only HEAD users can be listed as head users.'], $errors['head_user_mappings.0.employee_id']);
        $this->assertCount(3, $linked());

        $this->putJson($url, $base + ['head_user_mappings' => []])->assertStatus(422);
        $this->putJson($url, $base + ['head_user_mappings' => []], $this->withSpecialPassword())->assertOk();
        $this->assertSame([$this->employee->user_id], $linked());
    }

    public function test_only_head_users_can_be_chosen_as_heads_or_cash_heads(): void
    {
        // An EMPLOYEE (full form) so both lists reach validation.
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $this->employee->user_id;
        $base = $this->editBase($this->employee);

        foreach (['head_mappings', 'cash_head_mappings'] as $list) {
            $errors = $this->putJson($url, $base + [$list => [['head_id' => $this->customer->user_id]]], $this->withSpecialPassword())
                ->assertStatus(422)->json('errors');
            $this->assertSame(['Only HEAD users can be chosen as heads.'], $errors["{$list}.0.head_id"]);
        }
        $this->assertFalse(HeadEmployeeMapping::where('employee_id', $this->employee->user_id)->exists());

        $this->putJson($url, $base + ['head_mappings' => [['head_id' => $this->otherHead->user_id]]], $this->withSpecialPassword())
            ->assertOk();
        $this->assertSame(
            [$this->otherHead->user_id],
            HeadEmployeeMapping::where('employee_id', $this->employee->user_id)->pluck('head_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_a_blank_report_password_keeps_the_current_one(): void
    {
        $head = $this->makeUser('head_b', $this->headRole->id, '9600000011', ['report_password' => Hash::make('old-one')]);
        $this->signInToAdminConsoleAs($this->admin);

        $this->putJson('/api/v1/admin/user-details/' . $head->user_id, $this->editBase($head) + ['report_password' => ''])->assertOk();
        $this->assertTrue(Hash::check('old-one', $head->fresh()->report_password));

        $this->putJson('/api/v1/admin/user-details/' . $head->user_id, $this->editBase($head) + ['report_password' => 'ab'])
            ->assertStatus(422)->assertJsonValidationErrors(['report_password']);
    }

    public function test_a_heads_role_can_change_but_an_admins_cannot(): void
    {
        $head = $this->makeUser('head_c', $this->headRole->id, '9600000012');
        $adminUser = $this->makeUser('admin_user', $this->adminRole->id, '9600000013');
        $this->signInToAdminConsoleAs($this->admin);

        $this->putJson('/api/v1/admin/user-details/' . $head->user_id, $this->editBase($head) + ['role_id' => $this->employeeRole->id])
            ->assertOk();
        $this->assertSame($this->employeeRole->id, $head->fresh()->role_id);

        $this->putJson('/api/v1/admin/user-details/' . $adminUser->user_id, $this->editBase($adminUser) + ['role_id' => $this->headRole->id])
            ->assertStatus(422)->assertJsonValidationErrors(['role_id']);
        $this->assertSame($this->adminRole->id, $adminUser->fresh()->role_id);
    }

    public function test_sign_in_responses_never_carry_the_report_password_or_otp(): void
    {
        $user = $this->makeUser('head_d', $this->headRole->id, '9600000014', [
            'report_password' => Hash::make('rpt'), 'otp' => '1234',
        ]);

        $array = AuthUser::findOrFail($user->user_id)->toArray();
        $this->assertArrayNotHasKey('report_password', $array);
        $this->assertArrayNotHasKey('otp', $array);
        $this->assertArrayNotHasKey('password_hash', $array);

        Passport::actingAs(AuthUser::findOrFail($user->user_id));
        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonMissingPath('data.report_password')
            ->assertJsonMissingPath('data.otp');
    }

    public function test_the_app_refuses_features_a_users_permissions_leave_out(): void
    {
        $head = $this->makeUser('head_e', $this->headRole->id, '9600000015');
        Passport::actingAs(AuthUser::findOrFail($head->user_id));

        $this->getJson('/api/v1/phone-book')->assertStatus(403)
            ->assertJsonPath('message', "Your account doesn't have access to the Phone Book. Ask an admin to switch it on.");
        $this->postJson('/api/v1/stock/cash-out', [])->assertStatus(403);

        $head->update(['is_phone_book_need_shown' => true, 'is_cash_mngmt_need_to_shown' => true]);
        $this->app['auth']->forgetGuards();
        Passport::actingAs(AuthUser::findOrFail($head->user_id));

        $this->getJson('/api/v1/phone-book')->assertOk();
        // Past the permission: the request's own validation decides now.
        $this->postJson('/api/v1/stock/cash-out', [])->assertStatus(422);
    }
}
