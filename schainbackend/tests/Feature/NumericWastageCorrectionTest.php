<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\CashTxnDetail;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\StockInDetail;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use App\Services\StockInService;
use App\Services\StockOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §17: correcting a numeric wastage line (reversal + corrected
 * line) must leave every balance exactly as if the corrected values had been
 * entered in the first place, and keep the cash ledger's running snapshots
 * consistent.
 */
class NumericWastageCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $worker;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);

        $this->head = $this->makeUser('nw_head', $headRole->id, '9500000001', 100000, 900);
        $this->worker = $this->makeUser('nw_worker', $employeeRole->id, '9500000002', 5000, 45);

        $this->item = Item::create([
            'item_name' => 'Metal',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => false,
            'is_no_barcode' => true,
        ]);
    }

    private function makeUser(string $userName, int $roleId, string $phone, float $cash, float $purity): UserDetail
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
            'rak_cash_balance' => $cash,
            'rak_rtgs_balance' => 250,
            'grams_grand_total' => $purity * 1.1,
            'purity_grand_total' => $purity,
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    /** Every balance a numeric wastage line can touch, rounded for comparison. */
    private function balances(): array
    {
        $r = fn ($v) => round((float) $v, 3);
        $users = [];
        foreach ([$this->head, $this->worker] as $u) {
            $f = $u->fresh();
            $users[$u->user_id] = [$r($f->rak_cash_balance), $r($f->rak_rtgs_balance), $r($f->grams_grand_total), $r($f->purity_grand_total)];
        }
        $items = UsersItemsMapping::orderBy('user_id')->orderBy('item_id')->get()
            ->mapWithKeys(fn ($m) => ["{$m->user_id}:{$m->item_id}" => [$r($m->item_grams_total), $r($m->item_purity_total)]])
            ->all();

        return ['users' => $users, 'items' => $items];
    }

    private function line(array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->item->item_id,
            'grams' => 10,
            'touch' => 92,
            'no_of_pcs' => 4,
            'amount_pcs' => 50,
            'waste_total' => 0.1,
            'remarks' => 'original',
        ], $overrides);
    }

    /** Saves one line in $direction; returns its stock row id. */
    private function store(string $direction, array $line): int
    {
        if ($direction === 'in') {
            $records = app(StockInService::class)->createNumericWasteIn(
                ['given_by' => $this->worker->user_id, 'given_to' => $this->head->user_id, 'items' => [$line]],
                $this->head->user_id
            );

            return (int) $records[0]->stock_in_detail_id;
        }

        $records = app(StockOutService::class)->createNumericWaste(
            ['given_by' => $this->head->user_id, 'given_to' => $this->worker->user_id, 'items' => [$line]],
            $this->head->user_id
        );

        return (int) $records[0]->stock_id;
    }

    /** Each party's cash entries chain: opening = previous closing, last closing = balance. */
    private function assertCashLedgerChains(): void
    {
        foreach ([$this->head, $this->worker] as $user) {
            $id = $user->user_id;
            $previous = null;
            $rows = CashTxnDetail::where('sender_id', $id)->orWhere('recipient_id', $id)->orderBy('txn_id')->get();
            $this->assertNotEmpty($rows);
            foreach ($rows as $row) {
                $side = (int) $row->sender_id === $id ? 'sender' : 'recipient';
                foreach (['cash', 'rtgs'] as $dim) {
                    $this->assertNotNull($row->{"{$side}_closing_{$dim}"}, "txn #{$row->txn_id} {$side} closing {$dim}");
                    if ($previous) {
                        $this->assertEquals(
                            round((float) $previous[$dim], 2),
                            round((float) $row->{"{$side}_opening_{$dim}"}, 2),
                            "txn #{$row->txn_id} {$side} opening {$dim}"
                        );
                    }
                }
                $previous = ['cash' => $row->{"{$side}_closing_cash"}, 'rtgs' => $row->{"{$side}_closing_rtgs"}];
            }
            $fresh = $user->fresh();
            $this->assertEquals(round((float) $fresh->rak_cash_balance, 2), round((float) $previous['cash'], 2), "user {$id} cash");
            $this->assertEquals(round((float) $fresh->rak_rtgs_balance, 2), round((float) $previous['rtgs'], 2), "user {$id} rtgs");
        }
    }

    private function assertCorrectionMatchesDirectEntry(string $direction, array $original, array $changes): array
    {
        $this->signInAs($this->head);

        DB::beginTransaction();
        $this->store($direction, array_merge($original, $changes));
        $expected = $this->balances();
        DB::rollBack();

        $id = $this->store($direction, $original);
        $response = $this->postJson("/api/v1/numeric-wastage-entries/{$direction}/{$id}/correct", $changes)->assertOk();

        $this->assertEquals($expected, $this->balances());

        $key = $direction === 'in' ? 'stock_in_detail_id' : 'stock_id';
        $model = $direction === 'in' ? StockInDetail::class : StockDetails::class;
        $reversalId = $response->json("data.reversal.{$key}");
        $correctedId = $response->json("data.corrected.{$key}");
        $this->assertSame($correctedId, $model::find($id)->corrected_by_id);
        $this->assertSame($id, $model::find($reversalId)->reversal_of_id);
        $this->assertSame($id, $model::find($correctedId)->corrects_id);

        $this->assertCashLedgerChains();

        return [$id, $reversalId, $correctedId];
    }

    public function test_numeric_wastage_in_with_cash(): void
    {
        $this->assertCorrectionMatchesDirectEntry('in', $this->line(), ['grams' => 12.5, 'no_of_pcs' => 5, 'amount_pcs' => 60, 'waste_total' => 0.15]);
    }

    public function test_numeric_wastage_out_with_cash(): void
    {
        $this->assertCorrectionMatchesDirectEntry('out', $this->line(), ['grams' => 8, 'touch' => 91.6, 'amount_pcs' => 75]);
    }

    public function test_removing_the_cash_from_a_line(): void
    {
        [, , $correctedId] = $this->assertCorrectionMatchesDirectEntry('in', $this->line(), ['amount_pcs' => 0]);
        $this->assertNull(DB::table('numeric_wastage_in_records')->where('stock_in_detail_id', $correctedId)->value('cash_txn_id'));
    }

    public function test_rules(): void
    {
        $this->signInAs($this->head);
        $id = $this->store('out', $this->line());
        $before = $this->balances();
        $cashTxnId = (int) DB::table('numeric_wastages')->where('stock_id', $id)->value('cash_txn_id');

        // The cash entry points at its line and can't be edited on its own.
        $this->getJson("/api/v1/cash-txn-details/{$cashTxnId}")
            ->assertOk()
            ->assertJsonPath('data.numeric_wastage.direction', 'out')
            ->assertJsonPath('data.numeric_wastage.id', $id)
            ->assertJsonPath('data.edit_blocked_reason', 'This entry belongs to a numeric wastage entry; correct the numeric wastage entry instead.');

        // The prefill shows the original line.
        $this->getJson("/api/v1/numeric-wastage-entries/out/{$id}")
            ->assertOk()
            ->assertJsonPath('data.correct_blocked_reason', null)
            ->assertJsonPath('data.input.no_of_pcs', '4')
            ->assertJsonPath('data.input.amount_pcs', '50.0000');

        // A bad correction changes nothing.
        $this->postJson("/api/v1/numeric-wastage-entries/out/{$id}/correct", ['grams' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grams']);
        $this->assertEquals($before, $this->balances());
        $this->assertNull(StockDetails::find($id)->corrected_by_id);

        // Wrong direction or not a numeric wastage row → 404.
        $this->getJson("/api/v1/numeric-wastage-entries/in/{$id}")->assertStatus(404);

        // Only heads, even with "Can act as head".
        $this->worker->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->worker);
        $this->postJson("/api/v1/numeric-wastage-entries/out/{$id}/correct", ['remarks' => 'x'])->assertStatus(403);
        $this->getJson("/api/v1/numeric-wastage-entries/out/{$id}")->assertStatus(403);

        // Once corrected, the original and the reversal can't be corrected again.
        $this->signInAs($this->head);
        $result = $this->postJson("/api/v1/numeric-wastage-entries/out/{$id}/correct", ['remarks' => 'fixed'])->assertOk();
        $this->postJson("/api/v1/numeric-wastage-entries/out/{$id}/correct", ['remarks' => 'again'])->assertStatus(422);
        $reversalId = $result->json('data.reversal.stock_id');
        $this->postJson("/api/v1/numeric-wastage-entries/out/{$reversalId}/correct", ['remarks' => 'again'])->assertStatus(422);

        // A remarks-only correction leaves balances exactly where they were,
        // and the new cash entries say what they are.
        $this->assertEquals($before, $this->balances());
        $remarks = CashTxnDetail::where('txn_id', '>', $cashTxnId)->orderBy('txn_id')->pluck('remarks')->all();
        $this->assertCount(2, $remarks);
        $this->assertStringStartsWith('NUMERIC_WASTAGE EXPENSE', $remarks[0]);
        $this->assertStringEndsWith("reversal of #{$id}", $remarks[0]);
        $this->assertStringEndsWith("corrects #{$id}", $remarks[1]);
        $this->assertCashLedgerChains();
    }
}
