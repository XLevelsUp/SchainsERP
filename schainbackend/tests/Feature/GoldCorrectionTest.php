<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\BankDetail;
use App\Models\CashToGold;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use App\Services\CashToGoldService;
use App\Services\GoldToCashService;
use App\Services\PurchaseGoldService;
use App\Services\SaleGoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §17 phase 3: correcting a gold entry (reversal + corrected
 * entry) must leave every balance exactly as if the corrected values had been
 * entered in the first place.
 */
class GoldCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $customer;
    protected BankDetail $bank;
    protected Item $metal;
    protected StockDetails $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $headRole->id, '9400000001', 100000, 900);
        $this->customer = $this->makeUser('customer_a', $customerRole->id, '9400000002', 5000, 45);

        // GoldToCashService always books item 3, so make sure ids 1-3 exist.
        $this->metal = $this->item('Metal');
        $this->item('GMS Metal');
        $this->item('Chain');

        $columns = Schema::getColumnListing('bank_details');
        $bank = ['ledger_balance' => 100000];
        foreach (['account_name' => 'Test Account', 'bank_name' => 'Test Bank', 'account_number' => '0001',
            'ifsc_code' => 'TEST0001', 'is_active' => true] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $bank[$column] = $value;
            }
        }
        $this->bank = BankDetail::create($bank);

        // A metal lot the head holds, for Sale Gold to draw from.
        $this->lot = StockDetails::create([
            'item_id' => $this->metal->item_id,
            'given_by' => $this->customer->user_id,
            'given_to' => $this->head->user_id,
            'stock_type' => 'IN',
            'entry_type' => 'NORMAL',
            'type' => 'NORMAL',
            'grams' => 10,
            'touch' => 91.6,
            'purity' => 9.16,
            'balance' => 9.16,
            'added_by' => $this->head->user_id,
            'added_at' => now(),
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
            'rak_rtgs_balance' => 0,
            'grams_grand_total' => $purity * 1.1,
            'purity_grand_total' => $purity,
        ]);
    }

    private function item(string $name): Item
    {
        return Item::create([
            'item_name' => $name,
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => false,
            'is_no_barcode' => true,
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    /** Every balance a gold entry can touch, rounded for comparison. */
    private function balances(): array
    {
        $r = fn ($v) => round((float) $v, 3);
        $users = [];
        foreach ([$this->head, $this->customer] as $u) {
            $f = $u->fresh();
            $users[$u->user_id] = [$r($f->rak_cash_balance), $r($f->rak_rtgs_balance), $r($f->grams_grand_total), $r($f->purity_grand_total)];
        }
        $items = UsersItemsMapping::orderBy('user_id')->orderBy('item_id')->get()
            ->mapWithKeys(fn ($m) => ["{$m->user_id}:{$m->item_id}" => [$r($m->item_grams_total), $r($m->item_purity_total)]])
            ->all();

        return [
            'users' => $users,
            'items' => $items,
            'bank' => $r($this->bank->fresh()->ledger_balance),
            'lot' => $r($this->lot->fresh()->balance),
        ];
    }

    /**
     * Saves $original, corrects it with $changes through the API, and checks
     * the result against saving original+changes directly.
     */
    private function assertCorrectionMatchesDirectEntry(callable $store, array $original, array $changes): array
    {
        $this->signInAs($this->head);

        DB::beginTransaction();
        $store(array_merge($original, $changes));
        $expected = $this->balances();
        DB::rollBack();

        $record = $store($original);
        $response = $this->postJson("/api/v1/gold-entries/{$record->getKey()}/correct", $changes)->assertOk();

        $this->assertEquals($expected, $this->balances());

        $originalId = $record->getKey();
        $reversalId = $response->json('data.reversal.cash_to_gold_id');
        $correctedId = $response->json('data.corrected.cash_to_gold_id');
        $this->assertSame($correctedId, CashToGold::find($originalId)->corrected_by_id);
        $this->assertSame($originalId, CashToGold::find($reversalId)->reversal_of_id);
        $this->assertSame($originalId, CashToGold::find($correctedId)->corrects_id);

        return [$originalId, $reversalId, $correctedId];
    }

    public function test_cash_to_gold(): void
    {
        $base = [
            'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_cash' => 7200, 'per_gram_cash' => 7200, 'total_grams' => 1.005, 'touch' => 99.5, 'purity' => 1,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => true,
            'amount_sources' => [
                ['source' => 'CASH_ON_HAND', 'amount' => 5000],
                ['source' => 'BANK', 'bank_id' => $this->bank->bank_id, 'amount' => 2200],
            ],
        ];
        $this->assertCorrectionMatchesDirectEntry(
            fn ($d) => app(CashToGoldService::class)->store($d, $this->head->user_id),
            $base,
            ['total_cash' => 8640, 'total_grams' => 1.206, 'purity' => 1.2, 'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 8640]]]
        );
    }

    public function test_gold_to_cash(): void
    {
        $base = [
            'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_gold' => 2, 'touch' => 92, 'total_grams' => 2.174, 'per_gram_cash' => 7000, 'total_cash' => 14000,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 14000]],
        ];
        $this->assertCorrectionMatchesDirectEntry(
            fn ($d) => app(GoldToCashService::class)->store($d, $this->head->user_id),
            $base,
            ['total_gold' => 1.5, 'total_grams' => 1.63, 'total_cash' => 10500,
                'amount_sources' => [['source' => 'BANK', 'bank_id' => $this->bank->bank_id, 'amount' => 10500]]]
        );
    }

    public function test_purchase_gold(): void
    {
        $base = [
            'type' => 'HEAD', 'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_cash' => 7000, 'per_gram_cash' => 7000, 'total_grams' => 1, 'touch' => 100, 'purity' => 1,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => true,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 7000]],
        ];
        $this->assertCorrectionMatchesDirectEntry(
            fn ($d) => app(PurchaseGoldService::class)->store($d, $this->head->user_id),
            $base,
            ['total_cash' => 14000, 'total_grams' => 2, 'purity' => 2, 'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 14000]]]
        );
    }

    public function test_sale_gold_from_a_lot(): void
    {
        $base = [
            'type' => 'SALE_GOLD', 'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_cash' => 6000, 'per_gram_cash' => 6000, 'total_grams' => 1, 'touch' => 91.6, 'purity' => 0.916,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => false, 'stock_in_id' => $this->lot->stock_id,
        ];
        $this->assertCorrectionMatchesDirectEntry(
            fn ($d) => app(SaleGoldService::class)->store($d, $this->head->user_id),
            $base,
            ['total_cash' => 12000, 'total_grams' => 2, 'purity' => 1.832]
        );
    }

    /** §24: the entry_type CHECK used to reject IN_CASH_CONVERTER, so this never saved. */
    public function test_sale_gold_in_cash_converter_saves_and_corrects(): void
    {
        $this->signInAs($this->head);
        $payload = [
            'type' => 'IN_CASH_CONVERTER', 'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_cash' => 6000, 'per_gram_cash' => 6000, 'total_grams' => 1, 'touch' => 100, 'purity' => 1,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => true,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 6000]],
        ];

        $response = $this->postJson('/api/v1/sale-gold', $payload)->assertStatus(201);
        $id = $response->json('data.cash_to_gold_id') ?? $response->json('data.id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('stock_details', ['entry_type' => 'IN_CASH_CONVERTER', 'stock_type' => 'IN']);

        $this->assertCorrectionMatchesDirectEntry(
            fn ($d) => app(SaleGoldService::class)->store($d, $this->head->user_id),
            $payload,
            ['total_cash' => 12000, 'total_grams' => 2, 'purity' => 2, 'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 12000]]]
        );
    }

    /** The services' own rules come back as 422 with their message, not a bare 500. */
    public function test_gold_rule_failures_are_422_with_the_reason(): void
    {
        $this->signInAs($this->head);
        $base = [
            'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'per_gram_cash' => 7000, 'total_grams' => 1, 'touch' => 100, 'purity' => 1,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => true,
        ];

        // Purchase: more cash than the head has (100,000).
        $this->postJson('/api/v1/purchase-gold', $base + [
            'type' => 'HEAD', 'total_cash' => 200000,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 200000]],
        ])->assertStatus(422)->assertJsonPath('success', false)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Insufficient cash balance'))->etc());

        // Sale: sources don't add up to the total.
        $this->postJson('/api/v1/sale-gold', $base + [
            'type' => 'SALE_GOLD_CASH', 'total_cash' => 7000,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 5000]],
        ])->assertStatus(422)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Sum of amount sources'))->etc());
    }

    public function test_rules(): void
    {
        $this->signInAs($this->head);
        $record = app(PurchaseGoldService::class)->store([
            'type' => 'HEAD', 'head_id' => $this->head->user_id, 'customer_id' => $this->customer->user_id,
            'total_cash' => 7000, 'per_gram_cash' => 7000, 'total_grams' => 1, 'touch' => 100, 'purity' => 1,
            'item_id' => $this->metal->item_id, 'amnt_transfer_to_head' => true,
            'amount_sources' => [['source' => 'CASH_ON_HAND', 'amount' => 7000]],
        ], $this->head->user_id);
        $id = $record->getKey();
        $before = $this->balances();

        // A bad correction changes nothing (sources no longer add up).
        $this->postJson("/api/v1/gold-entries/{$id}/correct", ['total_cash' => 9000])->assertStatus(422);
        $this->assertEquals($before, $this->balances());
        $this->assertNull(CashToGold::find($id)->corrected_by_id);

        // Only heads.
        $this->customer->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->customer);
        $this->postJson("/api/v1/gold-entries/{$id}/correct", ['remarks' => 'x'])->assertStatus(403);
        $this->getJson("/api/v1/gold-entries/{$id}")->assertStatus(403);

        // The prefill shows the original input.
        $this->signInAs($this->head);
        $this->getJson("/api/v1/gold-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('data.kind', 'purchase')
            ->assertJsonPath('data.correct_blocked_reason', null)
            ->assertJsonPath('data.input.amount_sources.0.source', 'CASH_ON_HAND');

        // Once corrected, the original and the reversal can't be corrected again.
        $result = $this->postJson("/api/v1/gold-entries/{$id}/correct", ['remarks' => 'fixed'])->assertOk();
        $this->postJson("/api/v1/gold-entries/{$id}/correct", ['remarks' => 'again'])->assertStatus(422);
        $reversalId = $result->json('data.reversal.cash_to_gold_id');
        $this->postJson("/api/v1/gold-entries/{$reversalId}/correct", ['remarks' => 'again'])->assertStatus(422);
        // A remarks-only correction leaves balances exactly where they were.
        $this->assertEquals($before, $this->balances());
    }
}
