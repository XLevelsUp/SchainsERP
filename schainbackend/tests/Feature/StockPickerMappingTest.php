<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\HeadEmployeeMapping;
use App\Models\Item;
use App\Models\Role;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The Stock screen's pickers (2026-11-16) list only what is mapped to the
 * signed-in user: ?mapped_only=1 on GET /items and GET /user-details.
 * Without the flag both endpoints behave as before.
 */
class StockPickerMappingTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $otherHead;
    protected UserDetail $employee;
    protected UserDetail $customer;
    protected UserDetail $unmapped;
    protected Item $chain;
    protected Item $ring;
    protected Item $bangle;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $headRole->id, '9300000001');
        $this->otherHead = $this->makeUser('head_b', $headRole->id, '9300000002');
        $this->employee = $this->makeUser('employee_a', $employeeRole->id, '9300000003');
        $this->customer = $this->makeUser('customer_a', $customerRole->id, '9300000004');
        $this->unmapped = $this->makeUser('customer_b', $customerRole->id, '9300000005');

        // head_a → employee_a, customer_a; head_b → customer_b.
        $this->mapUser($this->head, $this->employee);
        $this->mapUser($this->head, $this->customer);
        $this->mapUser($this->otherHead, $this->unmapped);

        $this->chain = Item::create(['item_name' => 'Chain']);
        $this->ring = Item::create(['item_name' => 'Ring']);
        $this->bangle = Item::create(['item_name' => 'Bangle']);

        // head_a has Chain and Ring; employee_a has Bangle.
        $this->mapItem($this->head, $this->chain);
        $this->mapItem($this->head, $this->ring);
        $this->mapItem($this->employee, $this->bangle);
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

    private function mapUser(UserDetail $head, UserDetail $employee): void
    {
        HeadEmployeeMapping::create(['head_id' => $head->user_id, 'employee_id' => $employee->user_id]);
    }

    private function mapItem(UserDetail $user, Item $item): void
    {
        UsersItemsMapping::create(['user_id' => $user->user_id, 'item_id' => $item->item_id, 'is_primary' => 0]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->fresh()->user_id));
    }

    private function ids($response, string $key): array
    {
        $ids = array_column($response->json('data'), $key);
        sort($ids);

        return $ids;
    }

    public function test_items_mapped_only_returns_the_signed_in_users_items(): void
    {
        $this->signInAs($this->head);

        $mapped = $this->getJson('/api/v1/items?mapped_only=1')->assertOk();
        $this->assertSame([$this->chain->item_id, $this->ring->item_id], $this->ids($mapped, 'item_id'));

        $this->signInAs($this->employee);

        $mapped = $this->getJson('/api/v1/items?mapped_only=1')->assertOk();
        $this->assertSame([$this->bangle->item_id], $this->ids($mapped, 'item_id'));
    }

    public function test_items_without_the_flag_still_returns_every_item(): void
    {
        $this->signInAs($this->employee);

        $all = $this->getJson('/api/v1/items')->assertOk();
        $this->assertCount(3, $all->json('data'));
    }

    public function test_users_mapped_only_returns_the_signed_in_user_and_their_mapped_users(): void
    {
        $this->signInAs($this->head);

        $mapped = $this->getJson('/api/v1/user-details?module=stock&mapped_only=1')->assertOk();
        $expected = [$this->head->user_id, $this->employee->user_id, $this->customer->user_id];
        sort($expected);
        $this->assertSame($expected, $this->ids($mapped, 'id'));
    }

    public function test_users_mapped_only_applies_to_non_heads_too(): void
    {
        // Nobody is mapped to employee_a, so only their own row comes back
        // (the Stock screen's pickers then drop it).
        $this->signInAs($this->employee);

        $mapped = $this->getJson('/api/v1/user-details?module=stock&mapped_only=1')->assertOk();
        $this->assertSame([$this->employee->user_id], $this->ids($mapped, 'id'));
    }

    public function test_users_mapped_only_ignores_head_id_and_all(): void
    {
        $this->signInAs($this->head);

        $mapped = $this->getJson('/api/v1/user-details?module=stock&mapped_only=1&all=1&head_id=' . $this->otherHead->user_id)
            ->assertOk();
        $this->assertNotContains($this->unmapped->user_id, $this->ids($mapped, 'id'));
        $this->assertContains($this->employee->user_id, $this->ids($mapped, 'id'));
    }
}
