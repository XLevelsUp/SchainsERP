<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\BankDetail;
use App\Models\CashCategory;
use App\Models\CashTxnDetail;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * One Day Action (2026-11-25): the legacy screen's two sections, Stock
 * Report and Cash Management Report, each with its own total and paging,
 * in the app and the admin console. Today by default; filters User
 * (entered by), Items, Stock Type (IN/OUT), Given By, Given To, Role.
 */
class OneDayActionReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Role $employeeRole;
    protected Role $customerRole;
    protected UserDetail $headA;
    protected UserDetail $headB;
    protected UserDetail $employee;
    protected UserDetail $customer;
    protected Item $gold;
    protected Item $silver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);
        $this->headA = $this->makeUser('head_a', '9600000011', $this->headRole);
        $this->headB = $this->makeUser('head_b', '9600000012', $this->headRole);
        $this->employee = $this->makeUser('emp_a', '9600000013', $this->employeeRole);
        $this->customer = $this->makeUser('cust_a', '9600000014', $this->customerRole);
        $this->gold = Item::create(['item_name' => 'GOLD']);
        $this->silver = Item::create(['item_name' => 'SILVER']);
    }

    private function makeUser(string $userName, string $phone, Role $role): UserDetail
    {
        return UserDetail::create([
            'name' => strtoupper($userName), 'user_name' => $userName, 'password_hash' => Hash::make('pass-1234'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $role->id, 'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
    }

    private function stockRow(UserDetail $from, UserDetail $to, string $flow = 'OUT', ?Item $item = null, ?UserDetail $addedBy = null): StockDetails
    {
        return StockDetails::create([
            'item_id' => ($item ?? $this->gold)->item_id,
            'given_by' => $from->user_id, 'given_to' => $to->user_id,
            'stock_type' => $flow, 'entry_type' => 'NORMAL', 'type' => 'NORMAL',
            'grams' => 10, 'touch' => 92, 'purity' => 9.2, 'balance' => 9.2,
            'remarks' => 'zz one day', 'added_by' => ($addedBy ?? $from)->user_id, 'added_at' => now(),
        ]);
    }

    private function cashRow(UserDetail $sender, UserDetail $recipient, string $type = 'INCOME', array $extra = []): CashTxnDetail
    {
        return CashTxnDetail::create($extra + [
            'type' => $type, 'sender_id' => $sender->user_id, 'recipient_id' => $recipient->user_id,
            'amount' => 1000, 'payment_method' => 'CASH', 'added_by' => $recipient->user_id, 'remarks' => 'zz one day',
        ]);
    }

    private function signInAs(UserDetail $user): void
    {
        Passport::actingAs(AuthUser::findOrFail($user->user_id));
    }

    private function ids(array $data, string $section): array
    {
        return collect($data[$section]['records'])->pluck('id')->all();
    }

    public function test_the_app_returns_a_stock_and_a_cash_section_for_today(): void
    {
        $category = CashCategory::create(['category_name' => 'zz SALES', 'category_type' => 'INCOME', 'is_active' => true, 'added_by' => $this->headA->user_id]);
        $bank = BankDetail::create(['account_name' => 'zz BANK', 'ledger_balance' => 0, 'is_active' => true]);

        $stock = $this->stockRow($this->headA, $this->employee);
        $cash = $this->cashRow($this->employee, $this->headA, 'INCOME', [
            'category_id' => $category->category_id, 'payment_method' => 'BANK', 'bank_account_id' => $bank->bank_id,
        ]);
        // Yesterday's entries and another head's stay out.
        $old = $this->stockRow($this->headA, $this->employee);
        DB::table('stock_details')->where('stock_id', $old->stock_id)->update(['added_at' => now()->subDay()]);
        $oldCash = $this->cashRow($this->employee, $this->headA);
        DB::table('cash_txn_details')->where('txn_id', $oldCash->txn_id)->update(['created_at' => now()->subDay()]);
        $this->stockRow($this->headB, $this->employee);
        $this->cashRow($this->employee, $this->headB);

        $this->signInAs($this->headA);
        $data = $this->getJson('/api/v1/report/one-day-action')->assertOk()->json('data');

        $this->assertSame(1, $data['stock']['total_count']);
        $this->assertSame([$stock->stock_id], $this->ids($data, 'stock'));
        $this->assertSame(1, $data['cash']['total_count']);
        $this->assertSame([$cash->txn_id], $this->ids($data, 'cash'));

        $row = $data['cash']['records'][0];
        $this->assertSame('INCOME', $row['type']);
        $this->assertSame('IN', $row['flow']);
        $this->assertSame('zz SALES', $row['category_name']);
        $this->assertSame('BANK', $row['payment_method']);
        $this->assertSame('zz BANK', $row['bank_name']);
        $this->assertSame('EMP_A', $row['given_by']);
        $this->assertSame('HEAD_A', $row['given_to']);
        $this->assertSame('GOLD', $data['stock']['records'][0]['item_name']);

        // Yesterday, asked for, is there.
        $yesterday = now()->subDay()->toDateString();
        $past = $this->getJson("/api/v1/report/one-day-action?from_date={$yesterday}")->json('data');
        $this->assertSame([$old->stock_id], $this->ids($past, 'stock'));
        $this->assertSame([$oldCash->txn_id], $this->ids($past, 'cash'));

        // A range covers both days.
        $range = $this->getJson("/api/v1/report/one-day-action?from_date={$yesterday}&to_date=" . now()->toDateString())->json('data');
        $this->assertSame(2, $range['stock']['total_count']);
        $this->assertSame(2, $range['cash']['total_count']);
    }

    public function test_the_filters_narrow_both_sections(): void
    {
        $outGold = $this->stockRow($this->headA, $this->employee, 'OUT', $this->gold);
        $inSilver = $this->stockRow($this->customer, $this->headA, 'IN', $this->silver, $this->employee);
        $income = $this->cashRow($this->customer, $this->headA, 'INCOME');
        $expense = $this->cashRow($this->headA, $this->employee, 'EXPENSE');
        $this->signInAs($this->headA);
        $get = fn (string $qs) => $this->getJson("/api/v1/report/one-day-action?{$qs}")->assertOk()->json('data');

        // Stock Type: IN / OUT, on cash the Receive / Pay groups.
        $in = $get('type=IN');
        $this->assertSame([$inSilver->stock_id], $this->ids($in, 'stock'));
        $this->assertSame([$income->txn_id], $this->ids($in, 'cash'));
        $out = $get('type=OUT');
        $this->assertSame([$outGold->stock_id], $this->ids($out, 'stock'));
        $this->assertSame([$expense->txn_id], $this->ids($out, 'cash'));

        // Items: stock only; cash is untouched.
        $silver = $get('item_id=' . $this->silver->item_id);
        $this->assertSame([$inSilver->stock_id], $this->ids($silver, 'stock'));
        $this->assertSame(2, $silver['cash']['total_count']);

        // Given By / Given To.
        $byCustomer = $get('given_by=' . $this->customer->user_id);
        $this->assertSame([$inSilver->stock_id], $this->ids($byCustomer, 'stock'));
        $this->assertSame([$income->txn_id], $this->ids($byCustomer, 'cash'));
        $toEmployee = $get('given_to=' . $this->employee->user_id);
        $this->assertSame([$outGold->stock_id], $this->ids($toEmployee, 'stock'));
        $this->assertSame([$expense->txn_id], $this->ids($toEmployee, 'cash'));

        // User: who keyed the entry.
        $keyedByEmployee = $get('employee_id=' . $this->employee->user_id);
        $this->assertSame([$inSilver->stock_id], $this->ids($keyedByEmployee, 'stock'));
        $this->assertSame([$expense->txn_id], $this->ids($keyedByEmployee, 'cash'));

        // Role: either party has it.
        $customers = $get('role_id=' . $this->customerRole->id);
        $this->assertSame([$inSilver->stock_id], $this->ids($customers, 'stock'));
        $this->assertSame([$income->txn_id], $this->ids($customers, 'cash'));
        $employees = $get('role_id=' . $this->employeeRole->id);
        $this->assertSame([$outGold->stock_id], $this->ids($employees, 'stock'));
        $this->assertSame([$expense->txn_id], $this->ids($employees, 'cash'));
    }

    public function test_each_section_pages_on_its_own(): void
    {
        $stock = collect(range(1, 3))->map(fn () => $this->stockRow($this->headA, $this->employee));
        $cash = $this->cashRow($this->employee, $this->headA);
        $this->signInAs($this->headA);

        $first = $this->getJson('/api/v1/report/one-day-action?page_size=2')->json('data');
        $this->assertSame(3, $first['stock']['total_count']);
        $this->assertSame(2, $first['stock']['pagination']['total_pages']);
        $this->assertCount(2, $first['stock']['records']);
        $this->assertSame(1, $first['cash']['pagination']['total_pages']);

        // Load More on the stock section: only that section comes back.
        $more = $this->getJson('/api/v1/report/one-day-action?page_size=2&page_no=2&section=stock')->assertOk()->json('data');
        $this->assertArrayNotHasKey('cash', $more);
        $this->assertCount(1, $more['stock']['records']);

        $all = array_merge($this->ids($first, 'stock'), $this->ids($more, 'stock'));
        $this->assertEqualsCanonicalizing($stock->pluck('stock_id')->all(), $all);

        $cashOnly = $this->getJson('/api/v1/report/one-day-action?section=cash')->json('data');
        $this->assertArrayNotHasKey('stock', $cashOnly);
        $this->assertSame([$cash->txn_id], $this->ids($cashOnly, 'cash'));
    }

    public function test_bad_input_is_a_422(): void
    {
        $this->signInAs($this->headA);

        $this->getJson('/api/v1/report/one-day-action?type=SIDEWAYS&section=both&from_date=nope&page_size=5000&role_id=x')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'section', 'from_date', 'page_size', 'role_id']);
    }

    public function test_the_admin_console_covers_every_head_unless_narrowed(): void
    {
        $fromA = $this->stockRow($this->headA, $this->employee);
        $fromB = $this->stockRow($this->headB, $this->employee);
        $cashA = $this->cashRow($this->employee, $this->headA);
        $cashB = $this->cashRow($this->employee, $this->headB);
        $this->signInToAdminConsoleAs($this->makeAdmin());

        $all = $this->getJson('/api/v1/admin/reports/one-day-action')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$fromA->stock_id, $fromB->stock_id], $this->ids($all, 'stock'));
        $this->assertEqualsCanonicalizing([$cashA->txn_id, $cashB->txn_id], $this->ids($all, 'cash'));

        $onlyB = $this->getJson('/api/v1/admin/reports/one-day-action?head_id=' . $this->headB->user_id)->assertOk()->json('data');
        $this->assertSame([$fromB->stock_id], $this->ids($onlyB, 'stock'));
        $this->assertSame([$cashB->txn_id], $this->ids($onlyB, 'cash'));

        // The same filters as the app.
        $roles = $this->getJson('/api/v1/admin/reports/one-day-action?role_id=' . $this->customerRole->id)->json('data');
        $this->assertSame(0, $roles['stock']['total_count']);
        $this->assertSame(0, $roles['cash']['total_count']);

        $this->getJson('/api/v1/admin/reports/one-day-action?type=SIDEWAYS')
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }
}
