<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Switching a role on or off from the admin console's Roles page (agreed
 * 2026-11-23): it needs the special password, it is audited, and while a
 * role is off nobody on it can use the app: sign-in refuses them and a
 * session they already hold ends on its next request. Real Passport tokens,
 * as in AdminAuthTest.
 */
class RoleStatusTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    private const USER_PASSWORD = 'secret123';

    protected Role $headRole;
    protected Role $employeeRole;
    protected UserDetail $head;
    protected UserDetail $employee;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (!file_exists(Passport::keyPath('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--no-interaction' => true]);
        }
        $clients = app(ClientRepository::class);
        $clients->createPersonalAccessGrantClient('Test app client', 'users');
        $clients->createPersonalAccessGrantClient('Test admin client', 'admins');

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->head = $this->makeUser('head_a', $this->headRole->id, '9400000001');
        $this->employee = $this->makeUser('employee_a', $this->employeeRole->id, '9400000002');
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, int $roleId, string $phone): UserDetail
    {
        return UserDetail::create([
            'name' => ucfirst(str_replace('_', ' ', $userName)),
            'user_name' => $userName,
            'password_hash' => Hash::make(self::USER_PASSWORD),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $roleId,
            'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
    }

    private function appLogin(string $userName)
    {
        return $this->postJson('/api/v1/login', ['user_name' => $userName, 'password' => self::USER_PASSWORD]);
    }

    private function adminToken(): string
    {
        return $this->postJson('/api/v1/admin/login', ['user_name' => $this->admin->user_name, 'password' => $this->adminPassword])
            ->assertOk()
            ->json('data.token');
    }

    /** Each request authenticates afresh from its own bearer token. */
    private function asBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function setRoleStatus(string $adminToken, Role $role, mixed $isActive, ?string $specialPassword = null)
    {
        $headers = $specialPassword === '' ? [] : $this->withSpecialPassword($specialPassword);

        return $this->asBearer($adminToken)
            ->putJson("/api/v1/admin/roles/{$role->id}/status", ['is_active' => $isActive], $headers);
    }

    public function test_an_admin_switches_a_role_off_and_on_with_the_special_password(): void
    {
        $adminToken = $this->adminToken();

        $this->setRoleStatus($adminToken, $this->employeeRole, false)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role EMPLOYEE deactivated.')
            ->assertJsonPath('data.id', $this->employeeRole->id)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.users_count', 1);
        $this->assertFalse($this->employeeRole->fresh()->is_active);

        $this->assertDatabaseHas('system_logs', [
            'module' => 'ADMIN',
            'action' => 'ROLE_STATUS_CHANGE',
            'admin_id' => $this->admin->id,
            'user_id' => null,
            'reference_table' => 'roles',
            'reference_id' => $this->employeeRole->id,
        ]);

        // PATCH works too; only the status changes.
        $this->asBearer($adminToken)
            ->patchJson("/api/v1/admin/roles/{$this->employeeRole->id}/status", ['is_active' => true, 'role' => 'RENAMED'], $this->withSpecialPassword())
            ->assertOk()
            ->assertJsonPath('message', 'Role EMPLOYEE activated.')
            ->assertJsonPath('data.is_active', true);
        $this->assertSame('EMPLOYEE', $this->employeeRole->fresh()->role);
        $this->assertTrue($this->employeeRole->fresh()->is_active);
    }

    public function test_the_special_password_and_a_real_boolean_are_required(): void
    {
        $adminToken = $this->adminToken();

        $this->setRoleStatus($adminToken, $this->employeeRole, false, '')->assertStatus(422)
            ->assertJsonValidationErrors(['special_password']);
        $this->setRoleStatus($adminToken, $this->employeeRole, false, 'wrong')->assertStatus(422)
            ->assertJsonValidationErrors(['special_password']);
        $this->assertTrue($this->employeeRole->fresh()->is_active);

        $this->setRoleStatus($adminToken, $this->employeeRole, 'maybe')->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
        $this->asBearer($adminToken)
            ->putJson("/api/v1/admin/roles/{$this->employeeRole->id}/status", [], $this->withSpecialPassword())
            ->assertStatus(422)->assertJsonValidationErrors(['is_active']);

        $this->asBearer($adminToken)
            ->putJson('/api/v1/admin/roles/999999/status', ['is_active' => false], $this->withSpecialPassword())
            ->assertStatus(404);

        // Nothing failed was audited.
        $this->assertDatabaseMissing('system_logs', ['action' => 'ROLE_STATUS_CHANGE']);
    }

    public function test_app_tokens_cannot_switch_a_role(): void
    {
        $appToken = $this->appLogin('head_a')->assertOk()->json('data.token');

        $this->asBearer($appToken)
            ->putJson("/api/v1/admin/roles/{$this->employeeRole->id}/status", ['is_active' => false], $this->withSpecialPassword())
            ->assertStatus(401);
        $this->assertTrue($this->employeeRole->fresh()->is_active);
    }

    public function test_users_on_an_inactive_role_cannot_sign_in_until_it_is_back_on(): void
    {
        $adminToken = $this->adminToken();
        $this->setRoleStatus($adminToken, $this->employeeRole, false)->assertOk();

        $this->appLogin('employee_a')->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your role has been deactivated. Please contact your administrator.')
            ->assertJsonMissingPath('data.token');
        // A wrong password still reads as a wrong password, not as the role.
        $this->postJson('/api/v1/login', ['user_name' => 'employee_a', 'password' => 'wrong'])->assertStatus(401);
        // Other roles are unaffected.
        $this->appLogin('head_a')->assertOk();

        $this->setRoleStatus($adminToken, $this->employeeRole, true)->assertOk();
        $this->appLogin('employee_a')->assertOk()->assertJsonPath('data.user.user_name', 'employee_a');
    }

    public function test_switching_a_role_off_ends_its_users_open_sessions(): void
    {
        // Two sessions, so each app route group is checked with a live token.
        $first = $this->appLogin('employee_a')->assertOk()->json('data.token');
        $second = $this->appLogin('employee_a')->assertOk()->json('data.token');
        $headToken = $this->appLogin('head_a')->assertOk()->json('data.token');

        $this->setRoleStatus($this->adminToken(), $this->employeeRole, false)->assertOk();

        $this->asBearer($first)->getJson('/api/v1/me')->assertStatus(401)
            ->assertJsonPath('message', 'Your role has been deactivated. Please contact your administrator.');
        $this->asBearer($second)->getJson('/api/v1/stock/reports/id-wise')->assertStatus(401);

        $employeeTokens = DB::table('oauth_access_tokens')->where('user_id', $this->employee->user_id)
            ->whereNotNull('client_id')->pluck('revoked');
        $this->assertCount(2, $employeeTokens);
        $this->assertTrue($employeeTokens->every(fn ($revoked) => (bool) $revoked));

        // The head's session carries on.
        $this->asBearer($headToken)->getJson('/api/v1/me')->assertOk();

        // Back on: the ended sessions stay ended; signing in again works.
        $this->setRoleStatus($this->adminToken(), $this->employeeRole, true)->assertOk();
        $this->asBearer($first)->getJson('/api/v1/me')->assertStatus(401);
        $fresh = $this->appLogin('employee_a')->assertOk()->json('data.token');
        $this->asBearer($fresh)->getJson('/api/v1/me')->assertOk();
    }
}
