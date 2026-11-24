<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\Item;
use App\Models\Role;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Items and their order number (2026-11-24, the legacy Items page's "Orders
 * No"): every item list follows it, it's unique, a new item gets the next
 * one, and items are changed in the admin console only.
 */
class ItemOrderTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Admin $admin;
    protected UserDetail $head;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $this->head = UserDetail::create([
            'name' => 'Head A', 'user_name' => 'head_a', 'password_hash' => Hash::make('pass-1234'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => '9600000001', 'proff' => '-',
            'role_id' => $headRole->id, 'mailing_name' => 'head_a', 'category_name' => 'BOTH', 'system_id' => 'SYS_head_a',
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
        $this->admin = $this->makeAdmin();
    }

    public function test_every_item_list_follows_the_order_number(): void
    {
        Item::create(['item_name' => 'STONE', 'order_no' => 6]);
        Item::create(['item_name' => 'METAL', 'order_no' => 1]);
        Item::create(['item_name' => 'GOLD', 'order_no' => 2]);

        $this->signInToAdminConsoleAs($this->admin);
        $this->assertSame(['METAL', 'GOLD', 'STONE'], array_column($this->getJson('/api/v1/admin/items')->assertOk()->json('data'), 'item_name'));

        // The app's dropdowns read the same order.
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));
        $this->assertSame(['METAL', 'GOLD', 'STONE'], array_column($this->getJson('/api/v1/items')->assertOk()->json('data'), 'item_name'));
    }

    public function test_an_item_without_a_number_gets_the_next_one_and_numbers_are_unique(): void
    {
        Item::create(['item_name' => 'METAL', 'order_no' => 4]);
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/items', ['item_name' => 'GOLD'])->assertStatus(201)->assertJsonPath('data.order_no', 5);
        $this->postJson('/api/v1/admin/items', ['item_name' => 'FITEM', 'item_touch' => 91.7, 'order_no' => 3])
            ->assertStatus(201)->assertJsonPath('data.order_no', 3);

        $this->postJson('/api/v1/admin/items', ['item_name' => 'X', 'order_no' => 4])->assertStatus(422)
            ->assertJsonPath('errors.order_no.0', 'Another item already has this order number.');
        $this->postJson('/api/v1/admin/items', ['item_name' => 'X', 'order_no' => 0])->assertStatus(422)
            ->assertJsonPath('errors.order_no.0', 'The order number must be 1 or more.');

        // Saving an item's own number again is fine; taking another's isn't.
        $gold = Item::where('item_name', 'GOLD')->firstOrFail();
        $this->putJson("/api/v1/admin/items/{$gold->item_id}", ['item_name' => 'GOLD', 'order_no' => 5])->assertOk();
        $this->putJson("/api/v1/admin/items/{$gold->item_id}", ['order_no' => 3])->assertStatus(422);
        $this->putJson("/api/v1/admin/items/{$gold->item_id}", ['order_no' => 2])->assertOk()->assertJsonPath('data.order_no', 2);

        $this->assertSame(
            ['ITEM_CREATE', 'ITEM_CREATE', 'ITEM_UPDATE', 'ITEM_UPDATE'],
            DB::table('system_logs')->where('reference_table', 'items')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_an_item_in_use_cannot_be_deleted(): void
    {
        $used = Item::create(['item_name' => 'GOLD']);
        $unused = Item::create(['item_name' => 'OLD']);
        UsersItemsMapping::create(['user_id' => $this->head->user_id, 'item_id' => $used->item_id, 'item_grams_total' => '5', 'item_purity_total' => '0', 'is_primary' => 1, 'added_at' => now()]);
        $this->signInToAdminConsoleAs($this->admin);

        $this->deleteJson("/api/v1/admin/items/{$used->item_id}")->assertStatus(422)
            ->assertJsonPath('message', "GOLD is still used (users' items), so it can't be deleted. Switch it off instead (Is Active = No).");
        $this->assertTrue(UsersItemsMapping::where('item_id', $used->item_id)->exists());

        $this->deleteJson("/api/v1/admin/items/{$unused->item_id}")->assertOk();
        $this->assertNull(Item::find($unused->item_id));
    }

    public function test_heads_read_items_but_no_longer_change_them(): void
    {
        $item = Item::create(['item_name' => 'GOLD']);
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));

        $this->getJson('/api/v1/items')->assertOk();
        $this->postJson('/api/v1/items', ['item_name' => 'X'])->assertStatus(405);
        $this->putJson("/api/v1/items/{$item->item_id}", ['item_name' => 'Y'])->assertStatus(405);
        $this->deleteJson("/api/v1/items/{$item->item_id}")->assertStatus(405);
        $this->assertSame('GOLD', $item->fresh()->item_name);
    }
}
