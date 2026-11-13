<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §26: admin data is read by everyone and changed by heads only.
 * Creating a user is open to "Can act as head" users too, but they can't
 * create a head or hand out that permission.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Role $headRole;
    protected Role $customerRole;
    protected UserDetail $head;
    protected UserDetail $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $this->headRole->id, '9200000001');
        $this->employee = $this->makeUser('employee_a', $employeeRole->id, '9200000002');
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

    public function test_non_heads_can_read_admin_data_but_not_change_it(): void
    {
        $this->signInAs($this->employee);

        $this->getJson('/api/v1/items')->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/settings')->assertOk();

        $this->postJson('/api/v1/items', ['item_name' => 'X'])->assertStatus(403);
        $this->postJson('/api/v1/roles', ['role' => 'HEAD'])->assertStatus(403);
        $this->postJson('/api/v1/settings', ['setting_key' => 'k', 'setting_value' => [1]])->assertStatus(403);
        $this->postJson('/api/v1/bank-details', [])->assertStatus(403);
        $this->postJson('/api/v1/cash-categories', [])->assertStatus(403);
        $this->putJson('/api/v1/user-details/' . $this->head->user_id, ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/api/v1/user-details/' . $this->head->user_id)->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c1'))->assertStatus(403);
    }

    public function test_heads_can_change_admin_data(): void
    {
        $this->signInAs($this->head);

        // Past the gate: validation decides from here.
        $this->postJson('/api/v1/roles', [])->assertStatus(422);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->headRole->id, 'h1'))->assertStatus(201);
    }

    public function test_act_as_head_users_can_create_users_but_not_heads_or_the_permission(): void
    {
        $this->employee->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->employee);

        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c2'))->assertStatus(201);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->headRole->id, 'h2'))->assertStatus(403);
        $this->postJson('/api/v1/user-details', $this->newUserPayload($this->customerRole->id, 'c3', ['is_act_as_head_enabled' => true]))
            ->assertStatus(403);

        // Still can't edit users.
        $this->putJson('/api/v1/user-details/' . $this->head->user_id, ['name' => 'Hacked'])->assertStatus(403);
    }
}
