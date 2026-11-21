<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Stock history In/Out (GET /stock-details/history):
 *  - BACKEND_TODO §23: the In/Out filter must not drop rows whose entry_type
 *    it doesn't know; every row shows under exactly one of In and Out.
 *  - 2026-11-13: rows are labelled from the logged-in head's side even when
 *    a user is picked (the Stock screen always sends one), so a head's Stock
 *    Out shows as Out, and the label always matches the filter.
 */
class StockHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $h;
    private int $e;
    private int $x;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->h = $this->makeUser('sh_head', $headRole->id, '9600000001')->user_id;
        $this->e = $this->makeUser('sh_employee', $employeeRole->id, '9600000002')->user_id;
        $this->x = $this->makeUser('sh_other', $employeeRole->id, '9600000003')->user_id;
        $this->item = Item::create([
            'item_name' => 'Metal', 'is_active' => true, 'default_touch' => 92, 'item_touch' => 90,
            'is_need_fitem_shown' => false, 'is_barcode' => false, 'is_no_barcode' => true,
        ]);
    }

    public function test_every_row_is_labelled_from_the_heads_side_and_matches_the_filter(): void
    {
        [$h, $e, $x] = [$this->h, $this->e, $this->x];

        // [entry_type, saved stock_type, given_by, given_to, label for the head, label with $e picked]
        $rows = [
            ['NORMAL', 'OUT', $h, $e, 'OUT', 'OUT'],             // Stock Out (the reported bug: was In with $e picked)
            ['NORMAL', 'IN', $e, $h, 'IN', 'IN'],                // Stock In
            ['CashToGold', 'IN', $e, $h, 'IN', 'IN'],            // Cash to Gold
            ['GOLDCASHCONVERSION', 'OUT', $h, $e, 'OUT', 'OUT'], // Gold to Cash / Sale Gold Cash
            ['SALES', 'OUT', $h, $e, 'OUT', 'OUT'],              // Sale Gold
            ['OUT_CASH_CONVERTER', 'IN', $e, $h, 'IN', 'IN'],    // Purchase converter
            ['IN_CASH_CONVERTER', 'IN', $e, $h, 'IN', 'IN'],     // Sale converter (§24)
            ['EMPTOHEAD', 'OUT', $e, $h, 'IN', 'IN'],            // transfer saved as OUT: In for the head who received it
            ['HEADTOHEAD', 'OUT', $h, $e, 'OUT', 'OUT'],         // transfer: Out for the head who sent it
            ['EMPTOEMP', 'OUT', $e, $x, null, 'OUT'],            // not the head's: from the picked user's side
            ['EMPTOEMP', 'OUT', $x, $e, null, 'IN'],
        ];
        $expected = ['head' => [], 'picked' => []];
        foreach ($rows as [$entryType, $stockType, $by, $to, $forHead, $forPicked]) {
            $id = StockDetails::create([
                'item_id' => $this->item->item_id, 'given_by' => $by, 'given_to' => $to,
                'stock_type' => $stockType, 'entry_type' => $entryType, 'type' => 'NORMAL',
                'grams' => 1, 'touch' => 100, 'purity' => 1, 'balance' => 1,
                'added_by' => $h, 'added_at' => now(),
            ])->stock_id;
            if ($forHead) {
                $expected['head'][$id] = $forHead; // rows between the employees aren't in the head's own list
            }
            $expected['picked'][$id] = $forPicked;
        }

        Passport::actingAs(AuthUser::findOrFail($h));

        // Nobody picked, and the Stock screen's query with $e picked (it sends head_id = employee_id = picked user).
        foreach (['head' => "head_id={$h}", 'picked' => "head_id={$e}&employee_id={$e}"] as $view => $query) {
            $labels = $this->labels($query);
            ksort($labels);
            $want = $expected[$view];
            ksort($want);
            $this->assertSame($want, $labels, "labels, {$view} view");

            foreach (['IN', 'OUT'] as $type) {
                $ids = array_keys($this->labels("{$query}&type={$type}"));
                sort($ids);
                $this->assertSame(array_keys(array_filter($want, fn ($l) => $l === $type)), $ids, "{$type} filter, {$view} view");
            }
        }
    }

    /** stock_id => the In/Out label the history shows. */
    private function labels(string $query): array
    {
        return collect($this->getJson("/api/v1/stock-details/history?page_size=100&{$query}")->assertOk()->json('data.transactions.data'))
            ->mapWithKeys(fn ($row) => [$row['id'] => $row['stock_type']])
            ->all();
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
        ]);
    }
}
