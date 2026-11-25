<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CustomerTouch;
use App\Models\Item;
use App\Models\Role;
use App\Models\StockDetails;
use App\Models\SystemSetting;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The stock reports, Customer Touch and the company name in the admin
 * console (2026-11-24). Reports cover every head: Items OB & CB and
 * Consolidated per head (head_id required), One Day Action for all heads
 * unless narrowed, ID Wise for any stock ID.
 */
class AdminReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Admin $admin;
    protected UserDetail $headA;
    protected UserDetail $headB;
    protected UserDetail $employee;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $headRole = Role::create(['role' => 'HEAD']);
        $employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->headA = $this->makeUser('head_a', '9600000001', $headRole);
        $this->headB = $this->makeUser('head_b', '9600000002', $headRole);
        $this->employee = $this->makeUser('emp_a', '9600000003', $employeeRole);
        $this->item = Item::create(['item_name' => 'GOLD']);
        $this->admin = $this->makeAdmin();
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
            'remarks' => 'zz report row',
            'added_by' => $from->user_id,
            'added_at' => now(),
        ]);
    }

    public function test_one_day_action_covers_every_head_unless_narrowed(): void
    {
        $fromA = $this->stockRow($this->headA, $this->employee);
        $fromB = $this->stockRow($this->headB, $this->employee);
        $this->signInToAdminConsoleAs($this->admin);

        $all = $this->getJson('/api/v1/admin/reports/one-day-action')->assertOk()->json('data');
        $ids = collect($all['stock']['records'])->pluck('id')->all();
        $this->assertContains($fromA->stock_id, $ids);
        $this->assertContains($fromB->stock_id, $ids);

        $onlyA = $this->getJson('/api/v1/admin/reports/one-day-action?head_id=' . $this->headA->user_id)->assertOk()->json('data');
        $idsA = collect($onlyA['stock']['records'])->pluck('id')->all();
        $this->assertContains($fromA->stock_id, $idsA);
        $this->assertNotContains($fromB->stock_id, $idsA);

        // Only a HEAD user narrows it.
        $this->getJson('/api/v1/admin/reports/one-day-action?head_id=' . $this->employee->user_id)
            ->assertStatus(422)->assertJsonValidationErrors(['head_id']);
    }

    public function test_items_obcb_and_consolidated_run_per_head(): void
    {
        $this->stockRow($this->headA, $this->employee);
        $this->signInToAdminConsoleAs($this->admin);

        foreach (['items-obcb', 'consolidated'] as $report) {
            $this->getJson("/api/v1/admin/reports/{$report}")->assertStatus(422)
                ->assertJsonPath('errors.head_id.0', 'Pick a head.');
            $this->getJson("/api/v1/admin/reports/{$report}?head_id={$this->employee->user_id}")
                ->assertStatus(422)->assertJsonValidationErrors(['head_id']);
            $this->getJson("/api/v1/admin/reports/{$report}?head_id={$this->headA->user_id}")->assertOk();
        }

        $obcb = $this->getJson("/api/v1/admin/reports/items-obcb?head_id={$this->headA->user_id}&page_size=50&page_no=1")->json('data');
        $this->assertSame(1, $obcb['total_count'] ?? $obcb['details']['total_count'] ?? null);
        $this->assertSame(0, $this->getJson("/api/v1/admin/reports/items-obcb?head_id={$this->headB->user_id}")->json('data.total_count')
            ?? $this->getJson("/api/v1/admin/reports/items-obcb?head_id={$this->headB->user_id}")->json('data.details.total_count'));

        // Exports stream a CSV, per head.
        $csv = $this->get("/api/v1/admin/reports/items-obcb/export?head_id={$this->headA->user_id}");
        $csv->assertOk();
        $this->assertStringContainsString('history_items_obcb_head_' . $this->headA->user_id, (string) $csv->headers->get('content-disposition'));
        // A header row and the one movement (the export used to be empty).
        $lines = array_values(array_filter(explode("\n", trim($csv->streamedContent()))));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('stock_id', $lines[0]);
        $this->assertStringContainsString('zz report row', $lines[1]);

        // The app's own export, for the signed-in head, now has the row too.
        \Laravel\Passport\Passport::actingAs(\App\Models\AuthUser::findOrFail($this->headA->user_id));
        $appCsv = $this->get('/api/v1/stock/reports/items-obcb/export');
        $appCsv->assertOk();
        $this->assertStringContainsString('zz report row', $appCsv->streamedContent());
        $this->signInToAdminConsoleAs($this->admin);
        $this->get("/api/v1/admin/reports/consolidated/export?head_id={$this->headA->user_id}")->assertOk();
    }

    public function test_id_wise_opens_any_stock_id(): void
    {
        $row = $this->stockRow($this->headB, $this->employee);
        $this->signInToAdminConsoleAs($this->admin);

        $this->getJson('/api/v1/admin/reports/id-wise?stock_id=' . $row->stock_id)->assertOk();
        $this->getJson('/api/v1/admin/reports/id-wise?stock_id=999999')->assertNotFound();
        $this->getJson('/api/v1/admin/reports/id-wise')->assertStatus(422);
    }

    public function test_the_app_cannot_use_the_admin_reports(): void
    {
        \Laravel\Passport\Passport::actingAs(\App\Models\AuthUser::findOrFail($this->headA->user_id));
        $this->getJson('/api/v1/admin/reports/one-day-action')->assertStatus(401);
    }

    public function test_admins_manage_customer_touch_and_read_the_company_name(): void
    {
        // Seeded by the 2026_11_24_000200 migration.
        $this->signInToAdminConsoleAs($this->admin);

        $id = $this->postJson('/api/v1/admin/customer-touch', ['item_name' => 'zz 92 touch'])->assertStatus(201)->json('data.item_id');
        $this->putJson("/api/v1/admin/customer-touch/{$id}", ['item_name' => 'zz 91.6 touch'])->assertOk();
        $this->assertSame('zz 91.6 touch', CustomerTouch::find($id)->item_name);
        $this->deleteJson("/api/v1/admin/customer-touch/{$id}")->assertOk();
        $this->assertSame(
            ['CUSTOMER_TOUCH_CREATE', 'CUSTOMER_TOUCH_UPDATE', 'CUSTOMER_TOUCH_DELETE'],
            DB::table('system_logs')->where('reference_table', 'customer_touch')->orderBy('id')->pluck('action')->all(),
        );

        $this->getJson('/api/v1/admin/settings/company_name')->assertOk()->assertJsonPath('data.setting_value', 'NIHAA JEWELS');
    }
}
