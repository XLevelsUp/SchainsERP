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
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Item Change History and Item Conversion Group History (2026-11-25): what
 * the Stocks page's Item Change and Item Conversion recorded. The app shows
 * the signed-in user's own records plus a head's mapped employees'; the
 * console shows every user's, or one head's. Today by default; Item and
 * From / To Date filters. Conversions are grouped by item pair.
 */
class ItemHistoryReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    private UserDetail $headA;
    private UserDetail $headB;
    private UserDetail $employee;
    private Item $metal;
    private Item $chain;
    private Item $ring;
    private Item $copper;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->headA = $this->makeUser('ih_head_a', $headRole->id, '9710000001');
        $this->headB = $this->makeUser('ih_head_b', $headRole->id, '9710000002');
        // The employee may enter stock values (act as head) on their own stock.
        $this->employee = $this->makeUser('ih_emp', $employeeRole->id, '9710000003', ['is_act_as_head_enabled' => true]);
        HeadEmployeeMapping::create(['head_id' => $this->headA->user_id, 'employee_id' => $this->employee->user_id]);

        // Orders No: Ring (1) comes before Metal (2), Chain (3), Copper (4).
        $this->ring = $this->item('Ring', 1);
        $this->metal = $this->item('Metal', 2);
        $this->chain = $this->item('Chain', 3);
        $this->copper = $this->item('Copper', 4);

        foreach ([$this->headA, $this->headB, $this->employee] as $user) {
            foreach ([$this->metal, $this->ring] as $item) {
                UsersItemsMapping::create(['user_id' => $user->user_id, 'item_id' => $item->item_id,
                    'item_grams_total' => 100, 'item_purity_total' => 90, 'is_primary' => $item->is($this->metal)]);
            }
            $user->update(['grams_grand_total' => 200, 'purity_grand_total' => 180]);
        }
    }

    private function makeUser(string $userName, int $roleId, string $phone, array $extra = []): UserDetail
    {
        return UserDetail::create(array_merge([
            'name' => strtoupper($userName), 'user_name' => $userName, 'password_hash' => 'dummy',
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $roleId, 'mailing_name' => $userName, 'category_name' => 'BOTH',
            'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
            'rak_cash_balance' => 0, 'rak_rtgs_balance' => 0, 'grams_grand_total' => 0, 'purity_grand_total' => 0,
            'is_item_change_need_shown' => true, 'is_item_conversion_need_shown' => true,
        ], $extra));
    }

    private function item(string $name, int $orderNo): Item
    {
        return Item::create([
            'item_name' => $name, 'is_active' => true, 'default_touch' => 92, 'item_touch' => 90,
            'is_need_fitem_shown' => false, 'is_barcode' => false, 'is_no_barcode' => true, 'order_no' => $orderNo,
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    /** An Item Change through the Stocks page's endpoint. */
    private function change(UserDetail $user, Item $from, Item $to, float $grams, array $extra = [], ?string $addedAt = null): void
    {
        $this->signInAs($user);
        // The service reads the date from the top level, not from a line.
        $this->postJson('/api/v1/stock/item-change', array_filter(['added_at' => $addedAt]) + ['items' => [array_merge([
            'from_item_id' => $from->item_id, 'to_item_id' => $to->item_id,
            'grams' => $grams, 'from_touch' => 90, 'req_touch' => 99.9,
        ], $extra)]])->assertStatus(201);
    }

    /** An Item Conversion save (one or more lines) through its endpoint. */
    private function convert(UserDetail $user, array $lines): void
    {
        $this->signInAs($user);
        $this->postJson('/api/v1/stock/item-conversion', ['items' => $lines])->assertStatus(201);
    }

    private function line(Item $source, Item $target, float $grams, array $extra = []): array
    {
        return array_merge(['source_item_id' => $source->item_id, 'target_item_id' => $target->item_id,
            'source_grams' => $grams, 'source_touch' => 90, 'target_touch' => 75], $extra);
    }

    /* ------------------------------------------------------------ item change */

    public function test_the_app_shows_a_heads_own_and_mapped_employees_changes(): void
    {
        $this->change($this->headA, $this->metal, $this->chain, 10, ['remarks' => 'zz polish']);
        $this->change($this->employee, $this->metal, $this->chain, 5);
        $this->change($this->headB, $this->metal, $this->chain, 7);

        $this->signInAs($this->headA);
        $data = $this->getJson('/api/v1/stock/reports/item-changes')->assertOk()->json('data');

        $this->assertSame(2, $data['total_count']);
        $this->assertEqualsCanonicalizing([$this->headA->user_id, $this->employee->user_id], array_column($data['records'], 'user_id'));
        // Totals over the search: 15 g; From purity 15 × 90 % = 13.5; To purity 15 × 99.9 % = 14.985.
        $this->assertEquals(15, $data['totals']['grams']);
        $this->assertEquals(13.5, $data['totals']['from_purity']);
        $this->assertEquals(14.985, $data['totals']['purity']);

        $own = collect($data['records'])->firstWhere('user_id', $this->headA->user_id);
        $this->assertSame('IH_HEAD_A', $own['user_name']);
        $this->assertSame('Metal', $own['from_item_name']);
        $this->assertSame('Chain', $own['to_item_name']);
        $this->assertEquals(9, $own['from_purity']);
        $this->assertEquals(9.99, $own['purity']);
        $this->assertSame('zz polish', $own['remarks']);
        $this->assertNotNull($own['out_stock_id']);
        $this->assertNotNull($own['in_stock_id']);
        // The service's automatic remark isn't shown as one.
        $this->assertNull(collect($data['records'])->firstWhere('user_id', $this->employee->user_id)['remarks']);

        // The employee sees only their own; head B only theirs.
        $this->signInAs($this->employee);
        $this->assertSame([$this->employee->user_id],
            array_column($this->getJson('/api/v1/stock/reports/item-changes')->json('data.records'), 'user_id'));
        $this->signInAs($this->headB);
        $this->assertSame([$this->headB->user_id],
            array_column($this->getJson('/api/v1/stock/reports/item-changes')->json('data.records'), 'user_id'));
    }

    public function test_item_changes_filter_by_date_and_item(): void
    {
        $this->change($this->headA, $this->metal, $this->chain, 10);
        $this->change($this->headA, $this->ring, $this->copper, 4, [], now()->subDays(3)->format('Y-m-d H:i:s'));
        $this->signInAs($this->headA);

        // Today by default.
        $this->assertSame(1, $this->getJson('/api/v1/stock/reports/item-changes')->json('data.total_count'));

        $from = now()->subDays(5)->toDateString();
        $all = $this->getJson("/api/v1/stock/reports/item-changes?from_date={$from}&to_date=" . now()->toDateString())->json('data');
        $this->assertSame(2, $all['total_count']);
        // Newest first.
        $this->assertSame(['Metal', 'Ring'], array_column($all['records'], 'from_item_name'));

        // Item: either side of the change.
        $byTarget = $this->getJson("/api/v1/stock/reports/item-changes?from_date={$from}&item_id={$this->copper->item_id}&to_date=" . now()->toDateString());
        $this->assertSame(['Ring'], array_column($byTarget->json('data.records'), 'from_item_name'));

        // Paging.
        $page = $this->getJson("/api/v1/stock/reports/item-changes?from_date={$from}&to_date=" . now()->toDateString() . '&page_size=1&page_no=2')->json('data');
        $this->assertSame(['Ring'], array_column($page['records'], 'from_item_name'));
        $this->assertSame(2, $page['pagination']['total_pages']);
    }

    /* -------------------------------------------------------- item conversion */

    public function test_conversions_are_grouped_by_item_pair_in_orders_no_order(): void
    {
        // One save with two lines of different pairs, plus a later save.
        $this->convert($this->headA, [
            $this->line($this->metal, $this->chain, 10, ['remarks' => 'zz batch 1', 'alloys' => [
                ['alloy_item_id' => $this->copper->item_id, 'alloy_percentage' => 10, 'alloy_grams' => 1.2],
            ]]),
            $this->line($this->ring, $this->chain, 6),
        ]);
        $this->convert($this->employee, [$this->line($this->metal, $this->chain, 5)]);
        $this->convert($this->headB, [$this->line($this->metal, $this->chain, 8)]);

        $this->signInAs($this->headA);
        $data = $this->getJson('/api/v1/stock/reports/item-conversion-groups')->assertOk()->json('data');

        $this->assertSame(2, $data['total_count']);
        $this->assertSame(3, $data['total_conversions']);
        // Ring (Orders No 1) → Chain comes before Metal (2) → Chain.
        $this->assertSame(['Ring → Chain', 'Metal → Chain'],
            array_map(fn ($g) => "{$g['source_item_name']} → {$g['target_item_name']}", $data['groups']));

        $metal = $data['groups'][1];
        $this->assertSame(2, $metal['count']);
        $this->assertEqualsCanonicalizing([$this->headA->user_id, $this->employee->user_id], array_column($metal['records'], 'user_id'));
        // 15 g at 90 → at 75: 18 g; 13.5 pure either side; 1.2 g of alloy.
        $this->assertEquals(15, $metal['totals']['source_grams']);
        $this->assertEquals(18, $metal['totals']['converted_grams']);
        $this->assertEquals(13.5, $metal['totals']['source_purity']);
        $this->assertEquals(13.5, $metal['totals']['converted_purity']);
        $this->assertEquals(1.2, $metal['totals']['alloy_grams']);

        $withAlloy = collect($metal['records'])->firstWhere('user_id', $this->headA->user_id);
        $this->assertSame('zz batch 1', $withAlloy['remarks']);
        $this->assertSame('Copper', $withAlloy['alloys'][0]['alloy_item_name']);
        $this->assertEquals(10, $withAlloy['alloys'][0]['alloy_percentage']);
        $this->assertEquals(12, $withAlloy['converted_grams']);
        $this->assertNotNull($withAlloy['bill_id']);
        // The two lines of one save share its bill.
        $this->assertSame($withAlloy['bill_id'], $data['groups'][0]['records'][0]['bill_id']);
        $this->assertNull(collect($metal['records'])->firstWhere('user_id', $this->employee->user_id)['remarks']);

        // Item filter; group paging.
        $this->assertSame(['Ring'], array_column(
            $this->getJson("/api/v1/stock/reports/item-conversion-groups?item_id={$this->ring->item_id}")->json('data.groups'), 'source_item_name'));
        $page = $this->getJson('/api/v1/stock/reports/item-conversion-groups?page_size=1&page_no=2')->json('data');
        $this->assertSame(['Metal'], array_column($page['groups'], 'source_item_name'));
        $this->assertSame(2, $page['pagination']['total_pages']);

        // Head B sees only its own conversion.
        $this->signInAs($this->headB);
        $theirs = $this->getJson('/api/v1/stock/reports/item-conversion-groups')->json('data');
        $this->assertSame(1, $theirs['total_conversions']);
        $this->assertSame($this->headB->user_id, $theirs['groups'][0]['records'][0]['user_id']);
    }

    /* ---------------------------------------------------- access and validation */

    public function test_each_page_needs_its_features_permission(): void
    {
        $this->headA->update(['is_item_change_need_shown' => false]);
        $this->signInAs($this->headA);

        $this->getJson('/api/v1/stock/reports/item-changes')->assertStatus(403);
        $this->getJson('/api/v1/stock/reports/item-conversion-groups')->assertOk();

        $this->headA->update(['is_item_change_need_shown' => true, 'is_item_conversion_need_shown' => false]);
        $this->signInAs($this->headA);
        $this->getJson('/api/v1/stock/reports/item-changes')->assertOk();
        $this->getJson('/api/v1/stock/reports/item-conversion-groups')->assertStatus(403);
    }

    public function test_bad_filters_are_refused(): void
    {
        $this->signInAs($this->headA);

        $this->getJson('/api/v1/stock/reports/item-changes?from_date=not-a-date')->assertStatus(422);
        $this->getJson('/api/v1/stock/reports/item-changes?from_date=2026-11-20&to_date=2026-11-10')
            ->assertStatus(422)->assertJsonValidationErrors('to_date');
        $this->getJson('/api/v1/stock/reports/item-conversion-groups?page_size=5000')->assertStatus(422);
        $this->getJson('/api/v1/stock/reports/item-conversion-groups?item_id=abc')->assertStatus(422);
    }

    public function test_the_console_sees_every_user_or_one_heads_scope(): void
    {
        $this->change($this->headA, $this->metal, $this->chain, 10);
        $this->change($this->employee, $this->metal, $this->chain, 5);
        $this->change($this->headB, $this->metal, $this->chain, 7);
        $this->convert($this->headA, [$this->line($this->metal, $this->chain, 10)]);
        $this->convert($this->headB, [$this->line($this->ring, $this->chain, 8)]);

        $this->signInToAdminConsoleAs($this->makeAdmin());

        $this->assertSame(3, $this->getJson('/api/v1/admin/reports/item-changes')->assertOk()->json('data.total_count'));
        $narrowed = $this->getJson("/api/v1/admin/reports/item-changes?head_id={$this->headA->user_id}")->json('data.records');
        $this->assertEqualsCanonicalizing([$this->headA->user_id, $this->employee->user_id], array_column($narrowed, 'user_id'));

        $groups = $this->getJson('/api/v1/admin/reports/item-conversion-groups')->assertOk()->json('data');
        $this->assertSame(2, $groups['total_conversions']);
        $this->assertSame(1, $this->getJson("/api/v1/admin/reports/item-conversion-groups?head_id={$this->headB->user_id}")
            ->json('data.total_conversions'));

        // head_id must be a HEAD user.
        $this->getJson("/api/v1/admin/reports/item-changes?head_id={$this->employee->user_id}")->assertStatus(422);
        $this->getJson('/api/v1/admin/reports/item-conversion-groups?to_date=bad')->assertStatus(422);
    }

    public function test_the_app_cannot_use_the_console_reports(): void
    {
        $this->signInAs($this->headA);

        $this->getJson('/api/v1/admin/reports/item-changes')->assertStatus(401);
    }
}
