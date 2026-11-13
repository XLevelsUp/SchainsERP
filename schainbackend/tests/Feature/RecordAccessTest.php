<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\CashTxnDetail;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * BACKEND_TODO §3 / §7: a HEAD may read anyone's stock and cash records;
 * every other role only their own. The acting user comes from the token,
 * never from an X-User-ID header.
 */
class RecordAccessTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $otherHead;
    protected UserDetail $employee;
    protected UserDetail $customer;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $headRole->id, '9000000001');
        $this->otherHead = $this->makeUser('head_b', $headRole->id, '9000000002');
        $this->employee = $this->makeUser('employee_a', $employeeRole->id, '9000000003');
        $this->customer = $this->makeUser('customer_a', $customerRole->id, '9000000004');

        $this->item = Item::create([
            'item_name' => 'Gold Ring',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);
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

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    private function cashEntry(UserDetail $sender, UserDetail $recipient, string $type = 'INCOME'): CashTxnDetail
    {
        return CashTxnDetail::create([
            'type' => $type,
            'sender_id' => $sender->user_id,
            'recipient_id' => $recipient->user_id,
            'amount' => 1000,
            'payment_method' => 'CASH',
            'added_by' => $recipient->user_id,
        ]);
    }

    private function stockRow(UserDetail $from, UserDetail $to): StockDetails
    {
        return StockDetails::create([
            'item_id' => $this->item->item_id,
            'given_by' => $from->user_id,
            'given_to' => $to->user_id,
            'stock_type' => 'OUT',
            'entry_type' => 'NORMAL',
            'type' => 'NORMAL',
            'grams' => 10,
            'touch' => 92,
            'purity' => 9.2,
            'balance' => 9.2,
            'added_by' => $from->user_id,
            'added_at' => now(),
        ]);
    }

    public function test_non_head_cannot_read_another_users_stock_history(): void
    {
        $this->signInAs($this->employee);

        $this->getJson('/api/v1/stock-details/history?head_id=' . $this->head->user_id)->assertStatus(403);
        $this->getJson('/api/v1/stock-details/history?employee_id=' . $this->head->user_id)->assertStatus(403);
        $this->getJson('/api/v1/stock-details/history?head_id=' . $this->employee->user_id)->assertStatus(200);
    }

    public function test_head_can_read_any_users_stock_history(): void
    {
        $this->signInAs($this->head);

        $this->getJson('/api/v1/stock-details/history?head_id=' . $this->otherHead->user_id)->assertStatus(200);
        $this->getJson('/api/v1/stock-details/history?head_id=' . $this->customer->user_id)->assertStatus(200);
    }

    public function test_non_head_is_blocked_on_every_stock_report_that_names_another_user(): void
    {
        $this->signInAs($this->customer);
        $other = $this->head->user_id;

        $this->getJson("/api/v1/stock/reports/items-obcb?employee_id=user_{$other}")->assertStatus(403);
        $this->getJson("/api/v1/stock/reports/consolidated?head_id={$other}")->assertStatus(403);
        $this->getJson("/api/v1/stock-details/cash-transaction-history?head_id={$other}")->assertStatus(403);
        $this->getJson("/api/v1/stock-details/head-stocks?user_id={$other}")->assertStatus(403);
        $this->getJson("/api/v1/stock-details/available-lots?user_id={$other}&item_id={$this->item->item_id}")->assertStatus(403);
        $this->getJson("/api/v1/report/one-day-action?employee_id={$other}")->assertStatus(403);
        $this->getJson("/api/v1/report/daywise-grand?type4=EMPLOYEE&employee_id={$other}")->assertStatus(403);
    }

    public function test_non_head_cannot_open_a_stock_row_they_are_not_party_to(): void
    {
        $between = $this->stockRow($this->head, $this->otherHead);
        $mine = $this->stockRow($this->head, $this->customer);

        $this->signInAs($this->customer);

        $this->getJson('/api/v1/stock/reports/id-wise?stock_id=' . $between->stock_id)->assertStatus(403);
        $this->getJson('/api/v1/stock/reports/id-wise?stock_id=' . $mine->stock_id)->assertStatus(200);
    }

    public function test_cash_history_defaults_to_the_signed_in_user_and_ignores_x_user_id(): void
    {
        $this->cashEntry($this->customer, $this->head);     // head's entry
        $this->cashEntry($this->head, $this->employee);     // employee's entry

        $this->signInAs($this->employee);

        // Asking for the head's books by id is refused.
        $this->getJson('/api/v1/cash-txn-details/in-history?head_id=' . $this->head->user_id)->assertStatus(403);

        // Pretending to be the head through the old header gets your own entries only.
        $response = $this->withHeaders(['X-User-ID' => (string) $this->head->user_id])
            ->getJson('/api/v1/cash-txn-details/in-history')
            ->assertStatus(200);

        $recipients = collect($response->json('data.data'))->pluck('given_to_user.user_id')->unique()->values()->all();
        $this->assertSame([$this->employee->user_id], array_map('intval', $recipients));
    }

    public function test_non_head_cannot_print_someone_elses_receipt(): void
    {
        $theirs = $this->cashEntry($this->customer, $this->head);
        $mine = $this->cashEntry($this->employee, $this->head);

        $this->signInAs($this->employee);

        $this->getJson('/api/v1/cash-txn-details/print-report?id=' . $theirs->txn_id)->assertStatus(403);
        $this->getJson('/api/v1/cash-txn-details/print-report?id=' . $mine->txn_id)->assertStatus(200);
    }

    public function test_single_cash_entry_and_list_follow_the_same_rule(): void
    {
        $theirs = $this->cashEntry($this->customer, $this->head);
        $mine = $this->cashEntry($this->employee, $this->head);

        $this->signInAs($this->employee);
        $this->getJson('/api/v1/cash-txn-details/' . $theirs->txn_id)->assertStatus(403);
        $this->getJson('/api/v1/cash-txn-details/' . $mine->txn_id)->assertStatus(200);
        $this->getJson('/api/v1/cash-txn-details')->assertStatus(200)->assertJsonCount(1, 'data');

        $this->signInAs($this->head);
        $this->getJson('/api/v1/cash-txn-details')->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_broken_cash_write_routes_are_disabled(): void
    {
        $entry = $this->cashEntry($this->customer, $this->head);

        $this->signInAs($this->head);
        // No deletes (agreed 2026-11-12) and no raw create; edits go through
        // editEntry (CashEditTest).
        $this->deleteJson('/api/v1/cash-txn-details/' . $entry->txn_id)->assertStatus(405);
        $this->postJson('/api/v1/cash-txn-details', ['amount' => 1])->assertStatus(405);
        $this->assertDatabaseHas('cash_txn_details', ['txn_id' => $entry->txn_id]);
    }

    public function test_act_as_head_permission_opens_books_but_not_balance_reports(): void
    {
        $this->employee->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->employee);
        $head = $this->head->user_id;

        $this->getJson("/api/v1/stock-details/history?head_id={$head}")->assertStatus(200);
        $this->getJson("/api/v1/cash-txn-details/in-history?head_id={$head}")->assertStatus(200);
        $this->getJson("/api/v1/stock-details/head-stocks?user_id={$head}")->assertStatus(200);

        // Balance reports stay HEAD-only for someone else's books.
        $this->getJson("/api/v1/stock/reports/items-obcb?head_id={$head}")->assertStatus(403);
        $this->getJson("/api/v1/stock/reports/consolidated?head_id={$head}")->assertStatus(403);
    }

    public function test_balances_are_hidden_from_everyone_but_heads_and_the_owner(): void
    {
        $this->head->update(['rak_cash_balance' => 5000, 'grams_grand_total' => 100]);
        $this->employee->update(['is_act_as_head_enabled' => true, 'rak_cash_balance' => 10]);

        // Delegated user: other people's balances hidden, own balance visible.
        $this->signInAs($this->employee);
        $this->getJson('/api/v1/user-details/' . $this->head->user_id)
            ->assertStatus(200)
            ->assertJsonMissingPath('data.user.rak_cash_balance')
            ->assertJsonMissingPath('data.user.grams_grand_total');
        $this->getJson('/api/v1/user-details/' . $this->employee->user_id)
            ->assertStatus(200)
            ->assertJsonPath('data.user.rak_cash_balance', 10);

        $list = collect($this->getJson('/api/v1/user-details?module=cash')->assertStatus(200)->json('data'));
        $this->assertArrayNotHasKey('hand_cash', $list->firstWhere('id', $this->head->user_id));
        $this->assertArrayHasKey('hand_cash', $list->firstWhere('id', $this->employee->user_id));

        // Head: everything.
        $this->signInAs($this->head);
        $this->getJson('/api/v1/user-details/' . $this->employee->user_id)
            ->assertStatus(200)
            ->assertJsonPath('data.user.rak_cash_balance', 10);
    }

    public function test_cash_entry_shows_only_your_own_side_of_the_snapshot(): void
    {
        $entry = $this->cashEntry($this->employee, $this->head);
        $entry->update(['sender_opening_cash' => 50, 'recipient_opening_cash' => 9000]);

        $this->signInAs($this->employee);
        $this->getJson('/api/v1/cash-txn-details/' . $entry->txn_id)
            ->assertStatus(200)
            ->assertJsonPath('data.sender_opening_cash', '50.00')
            ->assertJsonMissingPath('data.recipient_opening_cash');
    }

    public function test_only_a_head_can_change_item_mappings_on_user_edit(): void
    {
        \App\Models\UsersItemsMapping::create([
            'user_id' => $this->customer->user_id,
            'item_id' => $this->item->item_id,
            'item_grams_total' => '25',
            'item_purity_total' => '23',
            'is_primary' => 1,
        ]);
        $base = [
            'name' => $this->customer->name,
            'user_name' => $this->customer->user_name,
            'address' => 'Test',
            'phone_no' => $this->customer->phone_no,
            'category_name' => 'BOTH',
        ];
        $blank = ['item_mappings' => [['item_id' => $this->item->item_id, 'item_grams_total' => '0', 'item_purity_total' => '0', 'is_primary' => 1]]];

        // A delegated non-head can't edit users at all (§26), so the totals stay.
        $this->employee->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->employee);
        $this->putJson('/api/v1/user-details/' . $this->customer->user_id, $base + $blank)->assertStatus(403);
        $this->assertDatabaseHas('users_items_mappings', ['user_id' => $this->customer->user_id, 'item_grams_total' => '25']);

        // A head's are applied.
        $this->signInAs($this->head);
        $this->putJson('/api/v1/user-details/' . $this->customer->user_id, $base + $blank)->assertStatus(200);
        $this->assertDatabaseHas('users_items_mappings', ['user_id' => $this->customer->user_id, 'item_grams_total' => '0']);
    }

    public function test_only_heads_and_permitted_users_can_enter_values(): void
    {
        $payload = ['given_by' => $this->customer->user_id, 'given_to' => $this->head->user_id, 'items' => []];

        $this->signInAs($this->customer);
        $this->postJson('/api/v1/stock/in', $payload)->assertStatus(403);
        $this->postJson('/api/v1/cash-txn-details/in', [])->assertStatus(403);

        // With the permission the request gets past the gate (and fails validation instead).
        $this->customer->update(['is_act_as_head_enabled' => true]);
        $this->signInAs($this->customer);
        $this->postJson('/api/v1/stock/in', $payload)->assertStatus(422);

        $this->signInAs($this->head);
        $this->postJson('/api/v1/cash-txn-details/in', [])->assertStatus(422);
    }

    public function test_cash_transactions_report_shows_non_heads_only_their_own_entries(): void
    {
        $this->cashEntry($this->customer, $this->head);
        $this->cashEntry($this->head, $this->otherHead);
        $this->cashEntry($this->employee, $this->head);

        $this->signInAs($this->employee);
        $this->getJson('/api/v1/report/cash-transactions-obcb?is_all=1')
            ->assertStatus(200)
            ->assertJsonPath('parameters.count', 1);

        $this->signInAs($this->head);
        $this->getJson('/api/v1/report/cash-transactions-obcb?is_all=1')
            ->assertStatus(200)
            ->assertJsonPath('parameters.count', 3);
    }
}
