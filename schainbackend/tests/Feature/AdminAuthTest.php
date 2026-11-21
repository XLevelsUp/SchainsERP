<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\SystemLog;
use App\Models\UserDetail;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The admin console's own sign-in (POST /api/v1/admin/login), with real
 * Passport tokens. Admins are their own accounts (table `admins`), not heads;
 * each realm's token works only in its own routes.
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    private const USER_PASSWORD = 'secret123';

    protected UserDetail $head;
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

        $headRole = Role::create(['role' => 'HEAD']);
        $this->head = UserDetail::create([
            'name' => 'Head A',
            'user_name' => 'head_a',
            'password_hash' => Hash::make(self::USER_PASSWORD),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => '9300000001', 'proff' => '-',
            'role_id' => $headRole->id,
            'mailing_name' => 'head_a', 'category_name' => 'BOTH', 'system_id' => 'SYS_HEAD_A',
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);

        // Same id as the head's user_id on a fresh database, on purpose: the
        // realms must not confuse admin #1 with user #1.
        $this->admin = $this->makeAdmin('admin_a');
    }

    private function adminLogin(string $userName, ?string $password = null)
    {
        return $this->postJson('/api/v1/admin/login', ['user_name' => $userName, 'password' => $password ?? $this->adminPassword]);
    }

    private function appLogin(): string
    {
        return $this->postJson('/api/v1/login', ['user_name' => 'head_a', 'password' => self::USER_PASSWORD])
            ->assertOk()
            ->json('data.token');
    }

    /** Each request authenticates afresh from its own bearer token. */
    private function asBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_an_admin_signs_in_and_gets_an_admin_scoped_token(): void
    {
        $this->assertSame($this->head->user_id, $this->admin->id);

        $response = $this->adminLogin('admin_a')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin.user_name', 'admin_a')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.admin.password_hash')
            ->assertJsonMissingPath('data.admin.special_password_hash');

        $this->assertNotEmpty($response->json('data.token'));

        $token = DB::table('oauth_access_tokens')->where('name', AdminAccess::TOKEN_NAME)->first();
        $this->assertSame([AdminAccess::SCOPE], json_decode($token->scopes, true));
        $this->assertNotNull($this->admin->fresh()->last_login_at);

        $this->assertDatabaseHas('system_logs', [
            'module' => 'ADMIN',
            'action' => 'ADMIN_LOGIN',
            'admin_id' => $this->admin->id,
            'user_id' => null,
            'reference_table' => 'admins',
        ]);
    }

    public function test_a_head_is_not_an_admin(): void
    {
        $this->postJson('/api/v1/admin/login', ['user_name' => 'head_a', 'password' => self::USER_PASSWORD])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid username or password');
    }

    public function test_bad_credentials_and_deactivated_admins_are_refused(): void
    {
        $this->adminLogin('admin_a', 'wrong-password')->assertStatus(401);
        $this->adminLogin('nobody')->assertStatus(401);
        // The special password is not a login password.
        $this->adminLogin('admin_a', $this->adminSpecialPassword)->assertStatus(401);

        $this->admin->update(['is_active' => false]);
        $this->adminLogin('admin_a')->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been deactivated. Please contact your administrator.');

        $this->assertSame(0, DB::table('oauth_access_tokens')->count());
        $this->assertSame(0, SystemLog::where('action', 'ADMIN_LOGIN')->count());
    }

    public function test_bad_input_is_a_422(): void
    {
        $this->postJson('/api/v1/admin/login', [])->assertStatus(422)
            ->assertJsonValidationErrors(['user_name', 'password']);
    }

    public function test_each_token_works_only_in_its_own_realm(): void
    {
        $adminToken = $this->adminLogin('admin_a')->json('data.token');
        $appToken = $this->appLogin();

        $this->asBearer($adminToken)->getJson('/api/v1/admin/me')->assertOk()
            ->assertJsonPath('data.id', $this->admin->id)
            ->assertJsonPath('data.user_name', 'admin_a');
        $this->asBearer($adminToken)->getJson('/api/v1/me')->assertStatus(401);
        $this->asBearer($adminToken)->getJson('/api/v1/items')->assertStatus(401);
        $this->asBearer($adminToken)->getJson('/api/v1/stock/reports/id-wise')->assertStatus(401);

        // Same numeric id, other realm: still refused.
        $this->asBearer($appToken)->getJson('/api/v1/me')->assertOk();
        $this->asBearer($appToken)->getJson('/api/v1/admin/me')->assertStatus(401);
        $this->asBearer($appToken)->getJson('/api/v1/admin/user-details')->assertStatus(401);
    }

    public function test_signing_out_revokes_only_the_admin_token(): void
    {
        $adminToken = $this->adminLogin('admin_a')->json('data.token');
        $appToken = $this->appLogin();

        $this->asBearer($adminToken)->postJson('/api/v1/admin/logout')->assertOk();
        $this->assertDatabaseHas('system_logs', ['module' => 'ADMIN', 'action' => 'ADMIN_LOGOUT', 'admin_id' => $this->admin->id]);

        $this->asBearer($adminToken)->getJson('/api/v1/admin/me')->assertStatus(401);
        $this->asBearer($appToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_deactivating_an_admin_ends_an_open_session(): void
    {
        $adminToken = $this->adminLogin('admin_a')->json('data.token');

        $this->admin->update(['is_active' => false]);

        $this->asBearer($adminToken)->getJson('/api/v1/admin/me')->assertStatus(401);
        $this->assertTrue((bool) DB::table('oauth_access_tokens')->where('name', AdminAccess::TOKEN_NAME)->value('revoked'));
    }

    public function test_sign_in_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->adminLogin('admin_a', 'wrong-password')->assertStatus(401);
        }

        $this->adminLogin('admin_a')->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
    }
}
