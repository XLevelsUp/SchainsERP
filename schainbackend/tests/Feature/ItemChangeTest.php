<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Item Change (POST /stock/item-change) and Item Conversion
 * (POST /stock/item-conversion), 2026-11-13: always on the logged-in user's
 * own stock, and they update the items' totals, the user's overall totals
 * and the head stock summary (live and as of a date/time).
 */
class ItemChangeTest extends TestCase
{
    use RefreshDatabase;

    private UserDetail $head;
    private UserDetail $other;
    private Item $metal;
    private Item $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->head = $this->makeUser('ic_head', $headRole->id, '9700000001');
        $this->other = $this->makeUser('ic_other', $employeeRole->id, '9700000002');
        $this->metal = $this->item('Metal');
        $this->chain = $this->item('Chain');

        // The head holds 50 g of Metal at 90 (45 pure); overall totals match.
        UsersItemsMapping::create(['user_id' => $this->head->user_id, 'item_id' => $this->metal->item_id,
            'item_grams_total' => 50, 'item_purity_total' => 45, 'is_primary' => 1]);
        $this->head->update(['grams_grand_total' => 50, 'purity_grand_total' => 45]);
    }

    private function line(array $overrides = []): array
    {
        return array_merge([
            'from_item_id' => $this->metal->item_id,
            'to_item_id' => $this->chain->item_id,
            'grams' => 10,
            'from_touch' => 90,
            'req_touch' => 99.9,
        ], $overrides);
    }

    public function test_changes_the_logged_in_users_own_items_and_totals(): void
    {
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));

        // No user is sent: it is the logged-in user's stock.
        $this->postJson('/api/v1/stock/item-change', ['items' => [$this->line()]])->assertStatus(201);

        $this->assertSame([40.0, 36.0], $this->itemTotals($this->head, $this->metal));   // 50 − 10 g, 45 − 9 pure
        $this->assertSame([10.0, 9.99], $this->itemTotals($this->head, $this->chain));   // 10 g at 99.9
        $head = $this->head->fresh();
        $this->assertEqualsWithDelta(50, (float) $head->grams_grand_total, 0.0001);      // grams don't change
        $this->assertEqualsWithDelta(45.99, (float) $head->purity_grand_total, 0.0001);  // 45 − 9 + 9.99
        $this->assertTotalsEqualItems($head);

        // The head stock summary shows it, live and as of a date/time (the
        // date view used to show Chain as Metal's balance minus the grams).
        foreach (['', '?head_txn_from_date=' . now()->toDateString() . '&head_txn_from_time=23:59:59'] as $query) {
            $items = collect($this->getJson('/api/v1/stock-details/head-stocks' . $query)->assertOk()->json('data.items'))
                ->keyBy('item_id');
            $this->assertEquals(40, $items[$this->metal->item_id]['grams'], "Metal grams {$query}");
            $this->assertEquals(36, $items[$this->metal->item_id]['purity'], "Metal purity {$query}");
            $this->assertEquals(10, $items[$this->chain->item_id]['grams'], "Chain grams {$query}");
            $this->assertEquals(9.99, $items[$this->chain->item_id]['purity'], "Chain purity {$query}");
        }

        // Nothing happened to anyone else.
        $this->assertSame(0, UsersItemsMapping::where('user_id', $this->other->user_id)->count());
    }

    public function test_draws_down_the_users_own_lot(): void
    {
        $lot = $this->lot($this->head, $this->metal, 30);
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));

        $this->postJson('/api/v1/stock/item-change', ['items' => [$this->line(['stock_in_id' => $lot->stock_id])]])
            ->assertStatus(201);

        $this->assertEqualsWithDelta(20, (float) $lot->fresh()->balance, 0.0001);
    }

    public function test_refuses_someone_elses_stock_or_lot(): void
    {
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));
        $before = [$this->itemTotals($this->head, $this->metal), (string) $this->head->fresh()->purity_grand_total];

        // Another user's id (what the screen used to send: the picked user).
        $this->postJson('/api/v1/stock/item-change', ['user_id' => $this->other->user_id, 'items' => [$this->line()]])
            ->assertStatus(422)->assertJsonPath('message', 'Item Change is for your own stock only.');

        // Another user's lot, and a lot of a different item.
        $theirs = $this->lot($this->other, $this->metal, 30);
        $this->postJson('/api/v1/stock/item-change', ['items' => [$this->line(['stock_in_id' => $theirs->stock_id])]])
            ->assertStatus(422)->assertJsonPath('message', "Lot #{$theirs->stock_id} isn't in your stock.");
        $chainLot = $this->lot($this->head, $this->chain, 30);
        $this->postJson('/api/v1/stock/item-change', ['items' => [$this->line(['stock_in_id' => $chainLot->stock_id])]])
            ->assertStatus(422);

        $this->assertSame($before, [$this->itemTotals($this->head, $this->metal), (string) $this->head->fresh()->purity_grand_total]);
        $this->assertEqualsWithDelta(30, (float) $theirs->fresh()->balance, 0.0001);

        // Sending your own id is fine (older clients).
        $this->postJson('/api/v1/stock/item-change', ['user_id' => $this->head->user_id, 'items' => [$this->line()]])
            ->assertStatus(201);
    }

    /** Item Conversion: the same rules, and its grams genuinely change. */
    public function test_conversion_changes_the_logged_in_users_own_items_and_totals(): void
    {
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));

        // 10 g Metal at 90 → Chain at 75: 10 × 90 ÷ 75 = 12 g, still 9 pure.
        $line = ['source_item_id' => $this->metal->item_id, 'target_item_id' => $this->chain->item_id,
            'source_grams' => 10, 'source_touch' => 90, 'target_touch' => 75];
        $this->postJson('/api/v1/stock/item-conversion', ['items' => [$line]])->assertStatus(201);

        $this->assertSame([40.0, 36.0], $this->itemTotals($this->head, $this->metal));
        $this->assertSame([12.0, 9.0], $this->itemTotals($this->head, $this->chain));
        $head = $this->head->fresh();
        $this->assertEqualsWithDelta(52, (float) $head->grams_grand_total, 0.0001);   // 50 − 10 + 12
        $this->assertEqualsWithDelta(45, (float) $head->purity_grand_total, 0.0001);  // 45 − 9 + 9
        $this->assertTotalsEqualItems($head);

        // Both rows record the target item's closing balance (it used to be saved as 0).
        $in = StockDetails::where('type', 'ITEMCONVERSION')->where('stock_type', 'IN')->firstOrFail();
        $this->assertEqualsWithDelta(12, (float) $in->given_to_item_grams_cb, 0.0001);
        $this->assertEqualsWithDelta(9, (float) $in->given_to_item_purity_cb, 0.0001);
        $this->assertEqualsWithDelta(40, (float) $in->given_by_item_grams_cb, 0.0001);

        foreach (['', '?head_txn_from_date=' . now()->toDateString() . '&head_txn_from_time=23:59:59'] as $query) {
            $items = collect($this->getJson('/api/v1/stock-details/head-stocks' . $query)->assertOk()->json('data.items'))
                ->keyBy('item_id');
            $this->assertEquals([40, 36], [$items[$this->metal->item_id]['grams'], $items[$this->metal->item_id]['purity']], "Metal {$query}");
            $this->assertEquals([12, 9], [$items[$this->chain->item_id]['grams'], $items[$this->chain->item_id]['purity']], "Chain {$query}");
        }

        // Refused, with nothing changed: another user's id, another user's lot, a target touch of 0.
        $before = [$this->itemTotals($this->head, $this->metal), (string) $this->head->fresh()->grams_grand_total];
        $this->postJson('/api/v1/stock/item-conversion', ['user_id' => $this->other->user_id, 'items' => [$line]])
            ->assertStatus(422)->assertJsonPath('message', 'Item Conversion is for your own stock only.');
        $theirs = $this->lot($this->other, $this->metal, 30);
        $this->postJson('/api/v1/stock/item-conversion', ['items' => [$line + ['stock_in_id' => $theirs->stock_id]]])
            ->assertStatus(422)->assertJsonPath('message', "Lot #{$theirs->stock_id} isn't in your stock.");
        $this->postJson('/api/v1/stock/item-conversion', ['items' => [array_merge($line, ['target_touch' => 0])]])
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.target_touch']);
        $this->assertSame($before, [$this->itemTotals($this->head, $this->metal), (string) $this->head->fresh()->grams_grand_total]);
        $this->assertSame(0, UsersItemsMapping::where('user_id', $this->other->user_id)->count());

        // Your own lot is drawn down.
        $mine = $this->lot($this->head, $this->metal, 30);
        $this->postJson('/api/v1/stock/item-conversion', ['items' => [$line + ['stock_in_id' => $mine->stock_id]]])->assertStatus(201);
        $this->assertEqualsWithDelta(20, (float) $mine->fresh()->balance, 0.0001);
    }

    /** [grams, purity] of one item for one user. */
    private function itemTotals(UserDetail $user, Item $item): array
    {
        $m = UsersItemsMapping::where('user_id', $user->user_id)->where('item_id', $item->item_id)->first();

        return [round((float) $m?->item_grams_total, 4), round((float) $m?->item_purity_total, 4)];
    }

    private function assertTotalsEqualItems(UserDetail $user): void
    {
        $maps = UsersItemsMapping::where('user_id', $user->user_id)->get();
        $this->assertEqualsWithDelta($maps->sum(fn ($m) => (float) $m->item_grams_total), (float) $user->grams_grand_total, 0.0001);
        $this->assertEqualsWithDelta($maps->sum(fn ($m) => (float) $m->item_purity_total), (float) $user->purity_grand_total, 0.0001);
    }

    private function lot(UserDetail $owner, Item $item, float $grams): StockDetails
    {
        return StockDetails::create([
            'item_id' => $item->item_id, 'given_by' => $this->other->user_id === $owner->user_id ? $this->head->user_id : $this->other->user_id,
            'given_to' => $owner->user_id, 'stock_type' => 'IN', 'entry_type' => 'NORMAL', 'type' => 'NORMAL',
            'grams' => $grams, 'touch' => 90, 'purity' => $grams * 0.9, 'balance' => $grams,
            'added_by' => $this->head->user_id, 'added_at' => now()->subHour(),
        ]);
    }

    private function item(string $name): Item
    {
        return Item::create([
            'item_name' => $name, 'is_active' => true, 'default_touch' => 92, 'item_touch' => 90,
            'is_need_fitem_shown' => false, 'is_barcode' => false, 'is_no_barcode' => true,
        ]);
    }

    private function makeUser(string $userName, int $roleId, string $phone): UserDetail
    {
        return UserDetail::create([
            'name' => $userName, 'user_name' => $userName, 'password_hash' => 'dummy',
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $roleId, 'mailing_name' => $userName, 'category_name' => 'BOTH',
            'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
            'rak_cash_balance' => 0, 'rak_rtgs_balance' => 0, 'grams_grand_total' => 0, 'purity_grand_total' => 0,
            // Item Change / Conversion each need their permission (2026-11-21).
            'is_item_change_need_shown' => true, 'is_item_conversion_need_shown' => true,
        ]);
    }

    public function test_item_change_and_conversion_need_their_permissions(): void
    {
        $this->head->update(['is_item_change_need_shown' => false, 'is_item_conversion_need_shown' => false]);
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));

        $this->postJson('/api/v1/stock/item-change', ['given_to' => $this->head->user_id, 'items' => [$this->line()]])
            ->assertStatus(403)
            ->assertJsonPath('message', "Your account doesn't have access to Item Change. Ask an admin to switch it on.");
        $this->postJson('/api/v1/stock/item-conversion', [])->assertStatus(403);
        $this->assertDatabaseHas('users_items_mappings', ['user_id' => $this->head->user_id, 'item_id' => $this->metal->item_id, 'item_grams_total' => 50]);
    }
}
