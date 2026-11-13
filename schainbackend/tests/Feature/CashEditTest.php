<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\BankDetail;
use App\Models\CashCategory;
use App\Models\CashTxnDetail;
use App\Models\Role;
use App\Models\UserDetail;
use App\Services\CashTxnDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §17 phase 1: heads can edit Cash In / Cash Out entries, and
 * the edit corrects balances, the bank and every later frozen snapshot.
 */
class CashEditTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $customer;
    protected UserDetail $other;
    protected BankDetail $bank;

    /** Balances each user started with, for the ledger-chain check. */
    protected array $initial = [];

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $headRole->id, '9300000001', 10000, 50000);
        $this->customer = $this->makeUser('customer_a', $customerRole->id, '9300000002', 1000, 0);
        $this->other = $this->makeUser('customer_b', $customerRole->id, '9300000003', 500, 0);

        $this->bank = BankDetail::create($this->bankAttributes());
    }

    private function bankAttributes(): array
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('bank_details');
        $attributes = ['ledger_balance' => 100000];
        foreach (['account_name' => 'Test Account', 'bank_name' => 'Test Bank', 'account_number' => '0001',
            'ifsc_code' => 'TEST0001', 'ifsc' => 'TEST0001', 'branch' => 'Main', 'branch_name' => 'Main',
            'account_no' => '0001', 'is_active' => true] as $column => $value) {
            if (in_array($column, $columns, true)) {
                $attributes[$column] = $value;
            }
        }
        return $attributes;
    }

    private function makeUser(string $userName, int $roleId, string $phone, float $cash, float $rtgs): UserDetail
    {
        $user = UserDetail::create([
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
            'rak_rtgs_balance' => $rtgs,
        ]);
        $this->initial[$user->user_id] = ['cash' => $cash, 'rtgs' => $rtgs];

        return $user;
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    private function cashIn(UserDetail $from, UserDetail $to, float $amount): CashTxnDetail
    {
        return app(CashTxnDetailService::class)->storeIncome([
            'sender_id' => $from->user_id,
            'recipient_id' => $to->user_id,
            'amount' => $amount,
            'payment_method' => 'CASH_ON_HAND',
        ], $this->head->user_id);
    }

    private function cashOut(UserDetail $from, UserDetail $to, float $amount): CashTxnDetail
    {
        return app(CashTxnDetailService::class)->storeExpense([
            'sender_id' => $from->user_id,
            'recipient_id' => $to->user_id,
            'amount' => $amount,
            'payment_method' => 'CASH_ON_HAND',
        ], $this->head->user_id);
    }

    /**
     * For every user: each entry's opening equals the previous entry's
     * closing (first one: the starting balance), and the last closing equals
     * the user's balance now. Cash and RTGS both.
     */
    private function assertLedgerChainIsConsistent(): void
    {
        foreach ($this->initial as $userId => $start) {
            $previous = $start;
            $entries = CashTxnDetail::where('sender_id', $userId)
                ->orWhere('recipient_id', $userId)
                ->orderBy('txn_id')
                ->get();

            foreach ($entries as $entry) {
                $side = (int) $entry->sender_id === (int) $userId ? 'sender' : 'recipient';
                foreach (['cash', 'rtgs'] as $dim) {
                    $this->assertEqualsWithDelta(
                        $previous[$dim],
                        (float) $entry->{"{$side}_opening_{$dim}"},
                        0.001,
                        "User {$userId}: entry #{$entry->txn_id} {$side} opening {$dim} doesn't follow on"
                    );
                    $previous[$dim] = (float) $entry->{"{$side}_closing_{$dim}"};
                }
            }

            $user = UserDetail::find($userId);
            $this->assertEqualsWithDelta($previous['cash'], (float) $user->rak_cash_balance, 0.001, "User {$userId}: cash balance");
            $this->assertEqualsWithDelta($previous['rtgs'], (float) $user->rak_rtgs_balance, 0.001, "User {$userId}: RTGS balance");
        }
    }

    public function test_editing_an_amount_corrects_balances_and_every_later_snapshot(): void
    {
        $this->cashIn($this->customer, $this->head, 500);
        $target = $this->cashIn($this->customer, $this->head, 300);
        $this->cashOut($this->head, $this->other, 200);
        $this->cashIn($this->other, $this->customer, 100);
        $this->assertLedgerChainIsConsistent();

        $this->signInAs($this->head);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['amount' => 450])
            ->assertOk()
            ->assertJsonPath('data.amount', '450.00');

        // Same as if 450 had been entered in the first place.
        $this->assertEqualsWithDelta(10000 + 500 + 450 - 200, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(1000 - 500 - 450 + 100, (float) $this->customer->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(500 + 200 - 100, (float) $this->other->fresh()->rak_cash_balance, 0.001);
        $this->assertLedgerChainIsConsistent();
    }

    public function test_switching_to_a_bank_payment_moves_rtgs_and_the_bank_and_back(): void
    {
        $target = $this->cashIn($this->customer, $this->head, 300);
        $this->cashOut($this->head, $this->other, 200);

        $this->signInAs($this->head);

        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", [
            'payment_method' => 'BANK',
            'bank_account_id' => $this->bank->bank_id,
        ])->assertOk();

        $this->assertEqualsWithDelta(10000 - 200, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(50000 + 300, (float) $this->head->fresh()->rak_rtgs_balance, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $this->customer->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(-300, (float) $this->customer->fresh()->rak_rtgs_balance, 0.001);
        $this->assertEqualsWithDelta(100300, (float) $this->bank->fresh()->ledger_balance, 0.001);
        $this->assertLedgerChainIsConsistent();

        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['payment_method' => 'CASH_ON_HAND'])->assertOk();

        $this->assertEqualsWithDelta(100000, (float) $this->bank->fresh()->ledger_balance, 0.001);
        $this->assertEqualsWithDelta(10000 + 300 - 200, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertNull($target->fresh()->bank_account_id);
        $this->assertLedgerChainIsConsistent();
    }

    public function test_other_fields_change_without_touching_balances(): void
    {
        $category = CashCategory::create(['category_name' => 'Office', 'is_active' => true, 'added_by' => $this->head->user_id]);
        $target = $this->cashIn($this->customer, $this->head, 300);

        $this->signInAs($this->head);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", [
            'category_id' => $category->category_id,
            'remarks' => 'corrected',
            'created_at' => '2026-11-01 10:30:00',
        ])->assertOk();

        $fresh = $target->fresh();
        $this->assertSame((int) $category->category_id, (int) $fresh->category_id);
        $this->assertSame('corrected', $fresh->remarks);
        $this->assertSame('2026-11-01 10:30:00', $fresh->created_at->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(10300, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertLedgerChainIsConsistent();
    }

    public function test_rules(): void
    {
        $target = $this->cashIn($this->customer, $this->head, 300);

        // Only heads.
        $this->customer->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->customer);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['amount' => 1])->assertStatus(403);

        $this->signInAs($this->head);

        // A bank payment needs a bank.
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['payment_method' => 'BANK'])->assertStatus(422);

        // Entries tied to other records wait for later phases.
        $target->update(['cash_to_gold_id' => 99]);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['amount' => 1])->assertStatus(422);
        $target->update(['cash_to_gold_id' => null, 'type' => 'SALE_GOLD']);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['amount' => 1])->assertStatus(422);

        // Nothing moved.
        $this->assertEqualsWithDelta(10300, (float) $this->head->fresh()->rak_cash_balance, 0.001);
    }

    /** Cash Auto Entry rows, created the real way (sender cash only). */
    private function autoEntry(UserDetail $head, UserDetail $cashUser, array $amounts): array
    {
        $this->signInAs($this->head);

        return app(\App\Services\CashAutoEntryService::class)->store([
            'type' => 'AUTO_ENTRY',
            'head_id' => $head->user_id,
            'cash_user_id' => $cashUser->user_id,
            'transactions' => array_map(fn ($a) => ['amount' => $a], $amounts),
        ]);
    }

    public function test_editing_a_cash_auto_entry_moves_only_the_sender(): void
    {
        [$first, $target] = $this->autoEntry($this->head, $this->customer, [100, 250]);
        $this->cashIn($this->customer, $this->head, 40);
        $this->assertLedgerChainIsConsistent();

        $this->signInAs($this->head);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", ['amount' => 300, 'remarks' => 'fixed'])
            ->assertOk()
            ->assertJsonPath('data.remarks', 'fixed');

        // Sender (head) lost 100 + 300 and got 40; the customer only moved by the later cash in.
        $this->assertEqualsWithDelta(10000 - 100 - 300 + 40, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(1000 - 40, (float) $this->customer->fresh()->rak_cash_balance, 0.001);
        $this->assertLedgerChainIsConsistent();
    }

    public function test_a_cash_auto_entry_stays_cash(): void
    {
        [$target] = $this->autoEntry($this->head, $this->customer, [100]);

        $this->signInAs($this->head);
        $this->putJson("/api/v1/cash-txn-details/{$target->txn_id}", [
            'payment_method' => 'BANK',
            'bank_account_id' => $this->bank->bank_id,
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(10000 - 100, (float) $this->head->fresh()->rak_cash_balance, 0.001);
        $this->assertEqualsWithDelta(100000, (float) $this->bank->fresh()->ledger_balance, 0.001);
    }

    public function test_show_tells_the_screen_whether_the_entry_can_be_edited(): void
    {
        $plain = $this->cashIn($this->customer, $this->head, 300);
        $gold = $this->cashIn($this->customer, $this->head, 100);
        $gold->update(['cash_to_gold_id' => 5]);

        $this->signInAs($this->head);
        $this->getJson("/api/v1/cash-txn-details/{$plain->txn_id}")->assertOk()->assertJsonPath('data.edit_blocked_reason', null);
        $this->assertNotNull($this->getJson("/api/v1/cash-txn-details/{$gold->txn_id}")->json('data.edit_blocked_reason'));
    }
}
