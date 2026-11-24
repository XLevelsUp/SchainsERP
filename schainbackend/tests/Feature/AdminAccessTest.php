<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\HeadEmployeeMapping;
use App\Models\Role;
use App\Models\SystemLog;
use App\Models\UserDetail;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Who changes what (agreed 2026-11-20):
 *  - Heads run the business in the app, administration included (BACKEND_TODO
 *    §26): items, roles, banks, cash categories, settings, customer touch, the
 *    audit log. Everyone signed in can read those.
 *  - Admins (their own accounts, not heads) create, edit and delete users and
 *    assign them to heads, in the admin console. Assigning heads and deleting
 *    a user need the admin's special password.
 *  - The app keeps one user creation: Add Retailer, a customer mapped to
 *    whoever adds it.
 *
 * The admin console's sign-in itself is covered by AdminAuthTest.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Role $employeeRole;
    protected Role $customerRole;
    protected UserDetail $head;
    protected UserDetail $otherHead;
    protected UserDetail $employee;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $this->headRole->id, '9200000001');
        $this->otherHead = $this->makeUser('head_b', $this->headRole->id, '9200000003');
        $this->employee = $this->makeUser('employee_a', $this->employeeRole->id, '9200000002');
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, int $roleId, string $phone): UserDetail
    {
        return UserDetail::create([
            'name' => ucfirst(str_replace('_', ' ', $userName)),
            'user_name' => $userName,
            'password_hash' => 'dummy',
            'address' => 'Test',
            'signature' => '-',
            'code' => '-',
            'phone_no' => $phone,
            'proff' => '-',
            'role_id' => $roleId,
            'mailing_name' => $userName,
            'category_name' => 'BOTH',
            'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true,
            'is_delete' => false,
            'is_billable' => true,
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->fresh()->user_id));
    }

    private function newUserPayload(int $roleId, string $suffix, array $extra = []): array
    {
        return array_merge([
            'name' => "New {$suffix}",
            'user_name' => "new_{$suffix}",
            'password' => 'secret123',
            'address' => 'Test',
            'signature' => '-',
            'code' => '-',
            'phone_no' => '93' . str_pad((string) crc32($suffix) % 100000000, 8, '0', STR_PAD_LEFT),
            'proff' => '-',
            'role_id' => $roleId,
            'mailing_name' => "New {$suffix}",
            'category_name' => 'BOTH',
            'system_id' => "SYS_NEW_{$suffix}",
        ], $extra);
    }

    /* ---------------------------------------------------------------- app */

    public function test_non_heads_can_read_admin_data_but_not_change_it(): void
    {
        $this->signInAs($this->employee);

        $this->getJson('/api/v1/items')->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/settings')->assertOk();

        // Items are changed in the admin console only (2026-11-24).
        $this->postJson('/api/v1/items', ['item_name' => 'X'])->assertStatus(405);
        // Roles are created in the admin console only.
        $this->postJson('/api/v1/roles', ['role' => 'HEAD'])->assertStatus(405);
        $this->postJson('/api/v1/settings', ['setting_key' => 'k', 'setting_value' => [1]])->assertStatus(403);
        $this->postJson('/api/v1/bank-details', [])->assertStatus(403);
        $this->postJson('/api/v1/cash-categories', [])->assertStatus(403);
        $this->getJson('/api/v1/system-logs')->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c1'))->assertStatus(403);
    }

    public function test_heads_read_items_and_manage_settings_and_the_audit_log_in_the_app(): void
    {
        $this->signInAs($this->head);

        // Items moved to the admin console (2026-11-24); heads still read them.
        $this->postJson('/api/v1/items', [])->assertStatus(405);
        $this->getJson('/api/v1/items')->assertOk();
        // Roles moved to the admin console; heads still read them.
        $this->getJson('/api/v1/roles')->assertOk();
        $this->postJson('/api/v1/roles', ['role' => 'X'])->assertStatus(405);
        $this->putJson('/api/v1/roles/' . $this->headRole->id, ['role' => 'X'])->assertStatus(405);
        $this->deleteJson('/api/v1/roles/' . $this->headRole->id)->assertStatus(405);
        $this->getJson('/api/v1/system-logs')->assertOk();
        $this->getJson('/api/v1/system-logs/filters')->assertOk();
    }

    public function test_heads_no_longer_edit_users_or_assign_heads_in_the_app(): void
    {
        $this->signInAs($this->head);

        $this->putJson('/api/v1/user-details/' . $this->employee->user_id, ['name' => 'X'])->assertStatus(405);
        $this->deleteJson('/api/v1/user-details/' . $this->employee->user_id)->assertStatus(405);
        $this->postJson('/api/v1/head-employee-mappings', [])->assertStatus(405);
        $this->postJson('/api/v1/cash-head-employee-mappings', [])->assertStatus(405);
        // The app token doesn't open the admin console either.
        $this->getJson('/api/v1/admin/user-details')->assertStatus(401);
    }

    public function test_add_retailer_creates_a_customer_mapped_to_whoever_adds_it(): void
    {
        $this->signInAs($this->head);
        $self = [['head_id' => $this->head->user_id]];

        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c1', ['head_mappings' => $self]))
            ->assertStatus(201);

        // Anything beyond that is the admin's job.
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->employeeRole->id, 'e1'))->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->headRole->id, 'h1'))->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c2', [
            'head_mappings' => [['head_id' => $this->otherHead->user_id]],
        ]))->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c3', [
            'cash_head_mappings' => $self,
        ]))->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c4', [
            'is_act_as_head_enabled' => true,
        ]))->assertStatus(403);
    }

    public function test_act_as_head_users_can_add_retailers_too(): void
    {
        $this->employee->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->employee);

        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c5', [
            'head_mappings' => [['head_id' => $this->employee->user_id]],
        ]))->assertStatus(201);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->headRole->id, 'h2'))->assertStatus(403);
    }

    /* ------------------------------------------------------ admin console */

    public function test_admins_create_users_without_heads_with_no_special_password(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        // Including heads and the "Can act as head" permission: setting up
        // users is the admin's job.
        $this->postJson('/api/v1/admin/user-details', $this->newUserPayload($this->headRole->id, 'h3'))->assertStatus(201);
        $this->postJson('/api/v1/admin/user-details', $this->newUserPayload($this->employeeRole->id, 'e2', [
            'is_act_as_head_enabled' => true,
        ]))->assertStatus(201);

        $created = UserDetail::where('user_name', 'new_h3')->firstOrFail();
        $this->assertDatabaseHas('system_logs', [
            'module' => 'ADMIN',
            'action' => 'USER_CREATE',
            'admin_id' => $this->admin->id,
            'user_id' => null,
            'reference_table' => 'user_details',
            'reference_id' => $created->user_id,
        ]);
        // The new user's password never reaches the log.
        $log = SystemLog::where('action', 'USER_CREATE')->firstOrFail();
        $this->assertSame('[REDACTED]', $log->payload['password']);
    }

    public function test_assigning_heads_needs_the_special_password(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $payload = $this->newUserPayload($this->employeeRole->id, 'e3', [
            'head_mappings' => [['head_id' => $this->head->user_id]],
        ]);

        $this->postJson('/api/v1/admin/user-details', $payload)->assertStatus(422)
            ->assertJsonValidationErrors(['special_password']);
        $this->postJson('/api/v1/admin/user-details', $payload, $this->withSpecialPassword('wrong'))->assertStatus(422)
            ->assertJsonPath('errors.special_password.0', 'The special password is incorrect.');
        // The login password is not the special password.
        $this->postJson('/api/v1/admin/user-details', $payload, $this->withSpecialPassword($this->adminPassword))->assertStatus(422);
        $this->assertDatabaseMissing('user_details', ['user_name' => 'new_e3']);

        $this->postJson('/api/v1/admin/user-details', $payload, $this->withSpecialPassword())->assertStatus(201);
        $created = UserDetail::where('user_name', 'new_e3')->firstOrFail();
        $this->assertTrue(HeadEmployeeMapping::where('employee_id', $created->user_id)->where('head_id', $this->head->user_id)->exists());

        // The mapping endpoints, always.
        $mapping = ['employee_id' => $this->employee->user_id, 'head_ids' => [$this->otherHead->user_id]];
        $this->postJson('/api/v1/admin/head-employee-mappings', $mapping)->assertStatus(422);
        $this->postJson('/api/v1/admin/head-employee-mappings', $mapping, $this->withSpecialPassword())->assertSuccessful();
        $this->putJson('/api/v1/admin/head-employee-mappings/' . $this->employee->user_id, ['head_ids' => [$this->head->user_id]])
            ->assertStatus(422);

        $this->assertTrue(SystemLog::where('action', 'HEAD_ASSIGN')->where('admin_id', $this->admin->id)->exists());
        $this->assertTrue((bool) SystemLog::where('action', 'USER_CREATE')->latest('id')->firstOrFail()->metadata['special_password_verified']);
    }

    public function test_deleting_a_user_needs_the_special_password(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $this->employee->user_id;

        $this->deleteJson($url)->assertStatus(422)->assertJsonValidationErrors(['special_password']);
        $this->assertDatabaseHas('user_details', ['user_id' => $this->employee->user_id]);

        $this->deleteJson($url, [], $this->withSpecialPassword())->assertOk();
        $this->assertDatabaseMissing('user_details', ['user_id' => $this->employee->user_id]);

        // The audit entry keeps who was deleted.
        $log = SystemLog::where('action', 'USER_DELETE')->firstOrFail();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('employee_a', $log->metadata['user_name']);
    }

    public function test_editing_a_user_needs_no_special_password(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->putJson('/api/v1/admin/user-details/' . $this->employee->user_id, [
            'name' => 'Renamed',
            'user_name' => 'employee_a',
            'address' => 'Test',
            'phone_no' => '9200000002',
            'category_name' => 'BOTH',
        ])->assertOk();
        $this->assertSame('Renamed', $this->employee->fresh()->name);
    }

    public function test_wrong_special_passwords_lock_the_check(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $this->employee->user_id;

        for ($i = 0; $i < 5; $i++) {
            $this->deleteJson($url, [], $this->withSpecialPassword('wrong'))->assertStatus(422);
        }
        // Even the right one waits out the lock.
        $this->deleteJson($url, [], $this->withSpecialPassword())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseHas('user_details', ['user_id' => $this->employee->user_id]);
    }

    public function test_the_admin_console_is_users_and_head_assignment_only(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        // Items are managed here since 2026-11-24 (the Items page). Roles can
        // be created and viewed, never edited or deleted.
        $this->getJson('/api/v1/admin/items')->assertOk();
        $this->postJson('/api/v1/admin/items', [])->assertStatus(422)->assertJsonValidationErrors(['item_name']);
        $this->putJson('/api/v1/admin/roles/' . $this->headRole->id, ['role' => 'X'])->assertStatus(405);
        $this->deleteJson('/api/v1/admin/roles/' . $this->headRole->id)->assertStatus(405);
        $this->getJson('/api/v1/admin/system-logs')->assertStatus(404);

        // And an admin session doesn't open the app.
        $this->getJson('/api/v1/items')->assertStatus(401);
        $this->postJson('/api/v1/stock/out', [])->assertStatus(401);
    }

    public function test_admins_create_and_view_roles(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        // Stored trimmed and in capitals, like the existing roles; active by default.
        $created = $this->postJson('/api/v1/admin/roles', ['role' => '  job work '])
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'JOB WORK')
            ->assertJsonPath('data.is_active', true)
            ->json('data');

        $this->postJson('/api/v1/admin/roles', ['role' => 'Chit', 'is_active' => false])
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'CHIT')
            ->assertJsonPath('data.is_active', false);

        // Unique ignoring case, required, and a real boolean.
        $this->postJson('/api/v1/admin/roles', ['role' => 'Head'])->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
        $this->postJson('/api/v1/admin/roles', ['role' => '   '])->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
        $this->postJson('/api/v1/admin/roles', ['role' => 'X', 'is_active' => 'maybe'])->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);

        $this->getJson('/api/v1/admin/roles/' . $created['id'])->assertOk()
            ->assertJsonPath('data.role', 'JOB WORK');
        // The list returns every role, inactive included, each with its status.
        $this->getJson('/api/v1/admin/roles')->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.role', 'CHIT')
            ->assertJsonPath('data.0.is_active', false);

        $this->assertDatabaseHas('system_logs', [
            'module' => 'ADMIN',
            'action' => 'ROLE_CREATE',
            'admin_id' => $this->admin->id,
            'reference_table' => 'roles',
            'reference_id' => $created['id'],
        ]);
    }

    public function test_an_admin_token_without_the_admin_scope_is_refused(): void
    {
        $this->signInToAdminConsoleAs($this->admin, []);

        $this->getJson('/api/v1/admin/me')->assertStatus(403);
    }

    public function test_admins_change_their_own_passwords_with_the_current_ones(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->putJson('/api/v1/admin/account/password', [
            'current_password' => 'wrong', 'password' => 'new-login-pass', 'password_confirmation' => 'new-login-pass',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);
        $this->putJson('/api/v1/admin/account/password', [
            'current_password' => $this->adminPassword,
            'password' => $this->adminSpecialPassword . 'x',
            'password_confirmation' => $this->adminSpecialPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->putJson('/api/v1/admin/account/password', [
            'current_password' => $this->adminPassword, 'password' => 'new-login-pass', 'password_confirmation' => 'new-login-pass',
        ])->assertOk();

        $this->putJson('/api/v1/admin/account/special-password', [
            'current_special_password' => 'wrong', 'special_password' => 'new-special', 'special_password_confirmation' => 'new-special',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_special_password']);
        // Must differ from the login password.
        $this->putJson('/api/v1/admin/account/special-password', [
            'current_special_password' => $this->adminSpecialPassword,
            'special_password' => 'new-login-pass',
            'special_password_confirmation' => 'new-login-pass',
        ])->assertStatus(422)->assertJsonValidationErrors(['special_password']);
        $this->putJson('/api/v1/admin/account/special-password', [
            'current_special_password' => $this->adminSpecialPassword,
            'special_password' => 'new-special',
            'special_password_confirmation' => 'new-special',
        ])->assertOk();

        $this->assertNotNull($this->admin->fresh()->special_password_changed_at);
        // Audited without the secrets.
        $log = SystemLog::where('action', 'ADMIN_SPECIAL_PASSWORD_CHANGE')->firstOrFail();
        $this->assertNull($log->payload);
        $this->assertSame($this->admin->id, $log->admin_id);
    }
}
