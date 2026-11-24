<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Item;
use App\Models\OpeningBalanceUpdate;
use App\Models\Role;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Opening balance updates (2026-11-24): the user form's Grams / Purity
 * (per item) and GRAMS / PURITY / CASH / RTGS modals. closing = previous +
 * value; an item update moves the grand total too; always the special
 * password; every change recorded.
 */
class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Admin $admin;
    protected Item $gold;
    protected Item $metal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->gold = Item::create(['item_name' => 'GOLD']);
        $this->metal = Item::create(['item_name' => 'Metal']);
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, string $phone): UserDetail
    {
        return UserDetail::create([
            'name' => $userName, 'user_name' => $userName, 'password_hash' => Hash::make('pass-1234'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $this->headRole->id, 'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
    }

    private function update(UserDetail $user, array $entry, bool $withPassword = true)
    {
        return $this->postJson(
            "/api/v1/admin/user-details/{$user->user_id}/opening-balance",
            $entry,
            $withPassword ? $this->withSpecialPassword() : [],
        );
    }

    public function test_an_item_update_adds_to_the_item_and_its_grand_total(): void
    {
        $user = $this->makeUser('head_a', '9600000001');
        UsersItemsMapping::create(['user_id' => $user->user_id, 'item_id' => $this->gold->item_id, 'item_grams_total' => '0', 'item_purity_total' => '0', 'is_primary' => 1, 'added_at' => now()]);
        $this->signInToAdminConsoleAs($this->admin);

        // The special password, always.
        $this->update($user, ['field' => 'ITEM_GRAMS', 'item_id' => $this->gold->item_id, 'value' => '10'], false)
            ->assertStatus(422)->assertJsonValidationErrors(['special_password']);

        $data = $this->update($user, ['field' => 'ITEM_GRAMS', 'item_id' => $this->gold->item_id, 'value' => '10.5', 'remarks' => 'Opening stock'])
            ->assertOk()->json('data');
        $this->assertEquals(10.5, $data['balances']['item']['item_grams_total']);
        $this->assertEquals(10.5, $data['balances']['grams_grand_total']);
        $this->assertEquals(0, $data['update']['previous_balance']);
        $this->assertEquals(10.5, $data['update']['closing_balance']);

        // A negative value reduces it.
        $this->update($user, ['field' => 'ITEM_GRAMS', 'item_id' => $this->gold->item_id, 'value' => '-2.25'])->assertOk();
        $this->update($user, ['field' => 'ITEM_PURITY', 'item_id' => $this->gold->item_id, 'value' => '9.1'])->assertOk();

        $mapping = UsersItemsMapping::where('user_id', $user->user_id)->first();
        $this->assertEquals(8.25, (float) $mapping->item_grams_total);
        $this->assertEquals(9.1, (float) $mapping->item_purity_total);
        $user->refresh();
        $this->assertEquals(8.25, (float) $user->grams_grand_total);
        $this->assertEquals(9.1, (float) $user->purity_grand_total);

        // Each item update records the item and the grand total it moved.
        $this->assertSame(
            ['ITEM_GRAMS', 'GRAMS', 'ITEM_GRAMS', 'GRAMS', 'ITEM_PURITY', 'PURITY'],
            OpeningBalanceUpdate::where('user_id', $user->user_id)->orderBy('id')->pluck('field')->all(),
        );
        $first = OpeningBalanceUpdate::where('user_id', $user->user_id)->orderBy('id')->first();
        $this->assertSame('Opening stock', $first->remarks);
        $this->assertSame($this->admin->id, $first->admin_id);
        $this->assertSame(3, DB::table('system_logs')->where('action', 'OPENING_BALANCE_UPDATE')->count());
    }

    public function test_the_grand_totals_cash_and_rtgs_change_on_their_own(): void
    {
        $user = $this->makeUser('head_b', '9600000002');
        UsersItemsMapping::create(['user_id' => $user->user_id, 'item_id' => $this->gold->item_id, 'item_grams_total' => '5', 'item_purity_total' => '0', 'is_primary' => 1, 'added_at' => now()]);
        $this->signInToAdminConsoleAs($this->admin);

        $this->update($user, ['field' => 'GRAMS', 'value' => '3'])->assertOk();
        $this->update($user, ['field' => 'PURITY', 'value' => '2.5'])->assertOk();
        $this->update($user, ['field' => 'CASH', 'value' => '1500.75'])->assertOk();
        $this->update($user, ['field' => 'RTGS', 'value' => '-200'])->assertOk();

        $user->refresh();
        $this->assertEquals(3, (float) $user->grams_grand_total);
        $this->assertEquals(2.5, (float) $user->purity_grand_total);
        $this->assertEquals(1500.75, (float) $user->rak_cash_balance);
        $this->assertEquals(-200, (float) $user->rak_rtgs_balance);
        // The item is untouched by a grand total update.
        $this->assertEquals(5, (float) UsersItemsMapping::where('user_id', $user->user_id)->value('item_grams_total'));
    }

    public function test_bad_entries_are_refused(): void
    {
        $user = $this->makeUser('head_c', '9600000003');
        $this->signInToAdminConsoleAs($this->admin);

        $this->update($user, ['field' => 'GRAMS', 'value' => '0'])->assertStatus(422)
            ->assertJsonPath('errors.value.0', 'Enter a value other than 0.');
        $this->update($user, ['field' => 'GRAMS', 'value' => '1.23456'])->assertStatus(422)->assertJsonValidationErrors(['value']);
        $this->update($user, ['field' => 'NOPE', 'value' => '1'])->assertStatus(422)->assertJsonValidationErrors(['field']);
        $this->update($user, ['field' => 'ITEM_GRAMS', 'value' => '1'])->assertStatus(422)
            ->assertJsonPath('errors.item_id.0', 'Pick the item to update.');
        // An item the user doesn't have.
        $this->update($user, ['field' => 'ITEM_GRAMS', 'item_id' => $this->metal->item_id, 'value' => '1'])->assertStatus(422)
            ->assertJsonPath('errors.value.0', "Add this item to the user's items first.");
        $this->postJson('/api/v1/admin/user-details/999999/opening-balance', ['field' => 'GRAMS', 'value' => '1'], $this->withSpecialPassword())
            ->assertNotFound();

        $this->assertSame(0, OpeningBalanceUpdate::count());
        $this->assertEquals(0, (float) $user->fresh()->grams_grand_total);
    }

    public function test_a_new_users_opening_balances_go_with_its_save(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $payload = [
            'name' => 'NEW HEAD', 'user_name' => 'new_head', 'password' => 'secret-12', 'phone_no' => '9600000004',
            'role_id' => $this->headRole->id, 'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => 'new_head', 'category_name' => 'BOTH', 'system_id' => 'SYS_new_head',
            'item_mappings' => [['item_id' => $this->gold->item_id, 'item_grams_total' => '0', 'item_purity_total' => '0', 'is_primary' => 1]],
            'opening_balances' => [
                ['field' => 'ITEM_GRAMS', 'item_id' => $this->gold->item_id, 'value' => '10', 'remarks' => 'Opening'],
                ['field' => 'ITEM_PURITY', 'item_id' => $this->gold->item_id, 'value' => '9.2'],
                ['field' => 'CASH', 'value' => '2500'],
            ],
        ];

        // Needs the special password.
        $this->postJson('/api/v1/admin/user-details', $payload)->assertStatus(422)->assertJsonValidationErrors(['special_password']);

        // An item that isn't being added is refused, and nothing is saved.
        $bad = $payload;
        $bad['opening_balances'][] = ['field' => 'ITEM_GRAMS', 'item_id' => $this->metal->item_id, 'value' => '1'];
        $this->postJson('/api/v1/admin/user-details', $bad, $this->withSpecialPassword())
            ->assertStatus(422)->assertJsonValidationErrors(['opening_balances.3.item_id']);
        $this->assertFalse(UserDetail::where('user_name', 'new_head')->exists());

        $this->postJson('/api/v1/admin/user-details', $payload, $this->withSpecialPassword())->assertStatus(201);
        $user = UserDetail::where('user_name', 'new_head')->firstOrFail();
        $mapping = UsersItemsMapping::where('user_id', $user->user_id)->firstOrFail();
        $this->assertEquals(10, (float) $mapping->item_grams_total);
        $this->assertEquals(9.2, (float) $mapping->item_purity_total);
        $this->assertEquals(10, (float) $user->grams_grand_total);
        $this->assertEquals(9.2, (float) $user->purity_grand_total);
        $this->assertEquals(2500, (float) $user->rak_cash_balance);
        $this->assertSame(5, OpeningBalanceUpdate::where('user_id', $user->user_id)->count());

        // Without opening balances, a new user needs no special password (no heads either).
        $plain = $payload;
        unset($plain['opening_balances']);
        $plain['user_name'] = 'plain_head';
        $plain['phone_no'] = '9600000005';
        $plain['system_id'] = 'SYS_plain';
        $this->postJson('/api/v1/admin/user-details', $plain)->assertStatus(201);
    }
}
