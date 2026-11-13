<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §4: heads and employees see every order; customers and
 * retailers only their own. index() is paginated and filterable.
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $employee;
    protected UserDetail $customerA;
    protected UserDetail $customerB;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->head = $this->makeUser('head_a', Role::create(['role' => 'HEAD'])->id, '9100000001');
        $this->employee = $this->makeUser('employee_a', Role::create(['role' => 'EMPLOYEE'])->id, '9100000002');
        $customerRole = Role::create(['role' => 'CUSTOMER'])->id;
        $this->customerA = $this->makeUser('customer_a', $customerRole, '9100000003');
        $this->customerB = $this->makeUser('customer_b', $customerRole, '9100000004');

        $this->item = Item::create([
            'item_name' => 'Gold Chain',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);

        $this->order($this->customerA, 'PENDING');
        $this->order($this->customerA, 'COMPLETED');
        $this->order($this->customerB, 'PENDING');
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

    private function order(UserDetail $customer, string $status): OrderDetail
    {
        return OrderDetail::create([
            'customer_id' => $customer->user_id,
            'item_id' => $this->item->item_id,
            'grams' => 10,
            'status' => $status,
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    public function test_heads_and_employees_see_every_order(): void
    {
        foreach ([$this->head, $this->employee] as $staff) {
            $this->signInAs($staff);
            $this->getJson('/api/v1/orders')
                ->assertOk()
                ->assertJsonPath('pagination.total', 3)
                ->assertJsonCount(3, 'data');
        }
    }

    public function test_customers_see_only_their_own_orders(): void
    {
        $this->signInAs($this->customerA);

        $response = $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('pagination.total', 2);
        $this->assertSame(
            [$this->customerA->user_id],
            collect($response->json('data'))->pluck('customer_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $this->getJson('/api/v1/orders?customer_id=' . $this->customerB->user_id)->assertStatus(403);
    }

    public function test_filters_and_paging(): void
    {
        $this->signInAs($this->head);

        $this->getJson('/api/v1/orders?status=PENDING')->assertOk()->assertJsonPath('pagination.total', 2);
        $this->getJson('/api/v1/orders?customer_id=' . $this->customerB->user_id)->assertOk()->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/v1/orders?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/orders?status=BOGUS')->assertStatus(422);
    }

    public function test_customers_cannot_open_change_or_delete_someone_elses_order(): void
    {
        $theirs = OrderDetail::where('customer_id', $this->customerB->user_id)->firstOrFail();

        $this->signInAs($this->customerA);

        $this->getJson("/api/v1/orders/{$theirs->order_id}")->assertStatus(403);
        $this->putJson("/api/v1/orders/{$theirs->order_id}", ['status' => 'CANCELLED'])->assertStatus(403);
        $this->deleteJson("/api/v1/orders/{$theirs->order_id}")->assertStatus(403);
        $this->assertDatabaseHas('order_details', ['order_id' => $theirs->order_id, 'status' => 'PENDING']);
    }

    public function test_customers_can_only_create_orders_for_themselves(): void
    {
        $this->signInAs($this->customerA);

        $payload = ['item_id' => $this->item->item_id, 'grams' => 5, 'status' => 'PENDING'];

        $this->postJson('/api/v1/orders', $payload + ['customer_id' => $this->customerB->user_id])->assertStatus(403);
        $this->postJson('/api/v1/orders', $payload + ['customer_id' => $this->customerA->user_id])->assertStatus(201);
    }

    public function test_remarks_and_due_date_are_ignored_instead_of_crashing(): void
    {
        $this->signInAs($this->head);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $this->customerA->user_id,
            'item_id' => $this->item->item_id,
            'grams' => 5,
            'status' => 'PENDING',
            'remarks' => 'not a column',
            'due_date' => '2026-12-01',
        ])->assertStatus(201);
    }
}
