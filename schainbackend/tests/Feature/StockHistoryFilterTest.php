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
 * BACKEND_TODO §23: the stock history's In/Out filter must not drop rows
 * whose entry_type it doesn't know. Every row shows under exactly one of In
 * and Out, so In + Out = All.
 */
class StockHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_and_out_together_cover_every_entry_type(): void
    {
        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $head = $this->makeUser('sh_head', $headRole->id, '9600000001');
        $employee = $this->makeUser('sh_employee', $employeeRole->id, '9600000002');
        $item = Item::create([
            'item_name' => 'Metal', 'is_active' => true, 'default_touch' => 92, 'item_touch' => 90,
            'is_need_fitem_shown' => false, 'is_barcode' => false, 'is_no_barcode' => true,
        ]);

        $h = $head->user_id;
        $e = $employee->user_id;
        // [entry_type, stock_type, given_by, given_to, expected filter]
        $rows = [
            ['NORMAL', 'OUT', $h, $e, 'OUT'],             // Stock Out
            ['NORMAL', 'IN', $e, $h, 'IN'],               // Stock In
            ['CashToGold', 'IN', $e, $h, 'IN'],           // Cash to Gold
            ['GOLDCASHCONVERSION', 'OUT', $h, $e, 'OUT'], // Gold to Cash / Sale Gold Cash
            ['SALES', 'OUT', $h, $e, 'OUT'],              // Sale Gold
            ['OUT_CASH_CONVERTER', 'IN', $e, $h, 'IN'],   // Purchase converter
            ['IN_CASH_CONVERTER', 'IN', $e, $h, 'IN'],    // Sale converter (§24)
            ['EMPTOHEAD', 'OUT', $e, $h, 'IN'],           // transfer: IN for its receiver
            ['HEADTOHEAD', 'OUT', $h, $e, 'OUT'],         // transfer: OUT for its sender
        ];
        $expected = ['IN' => [], 'OUT' => []];
        foreach ($rows as [$entryType, $stockType, $by, $to, $filter]) {
            $row = StockDetails::create([
                'item_id' => $item->item_id, 'given_by' => $by, 'given_to' => $to,
                'stock_type' => $stockType, 'entry_type' => $entryType, 'type' => 'NORMAL',
                'grams' => 1, 'touch' => 100, 'purity' => 1, 'balance' => 1,
                'added_by' => $h, 'added_at' => now(),
            ]);
            $expected[$filter][] = $row->stock_id;
        }

        Passport::actingAs(AuthUser::findOrFail($h));
        $ids = fn (?string $type) => collect(
            $this->getJson('/api/v1/stock-details/history?page_size=100' . ($type ? "&type={$type}" : ''))
                ->assertOk()->json('data.transactions.data')
        )->pluck('id')->sort()->values()->all();

        $this->assertSame(collect($expected['IN'])->sort()->values()->all(), $ids('IN'));
        $this->assertSame(collect($expected['OUT'])->sort()->values()->all(), $ids('OUT'));
        $this->assertCount(count($rows), $ids(null));
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
