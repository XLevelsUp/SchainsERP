<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\CashHeadEmployeeMapping;
use App\Models\CustomerTouch;
use App\Models\CustomerTouchUserMapping;
use App\Models\HeadEmployeeMapping;
use App\Models\Item;
use App\Models\Role;
use App\Models\SystemLog;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The admin console's role-driven user form (2026-11-21): each role's
 * profile decides which sections and permissions can be set
 * (App\Support\UserFormProfiles), plus the legacy form's sections —
 * joining date, customer touches, heads editable on edit, items with
 * read-only totals — and the Users list's status toggle.
 */
class AdminUserFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Role $employeeRole;
    protected Role $adminRole;
    protected UserDetail $head;
    protected UserDetail $otherHead;
    protected UserDetail $employee;
    protected Item $gold;
    protected Item $silver;
    protected CustomerTouch $touch91;
    protected CustomerTouch $touch92;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->adminRole = Role::create(['role' => 'ADMIN']);

        $this->head = $this->makeUser('head_a', $this->headRole->id, '9400000001');
        $this->otherHead = $this->makeUser('head_b', $this->headRole->id, '9400000002');
        $this->employee = $this->makeUser('employee_a', $this->employeeRole->id, '9400000003');

        $this->gold = $this->item('Gold');
        $this->silver = $this->item('Silver');
        $this->touch91 = CustomerTouch::create(['item_name' => '91.6', 'is_active' => true]);
        $this->touch92 = CustomerTouch::create(['item_name' => '92.0', 'is_active' => true]);

        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, int $roleId, string $phone): UserDetail
    {
        return UserDetail::create([
            'name' => ucfirst(str_replace('_', ' ', $userName)),
            'user_name' => $userName,
            'password_hash' => 'dummy',
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $roleId,
            'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . strtoupper($userName),
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
    }

    private function item(string $name): Item
    {
        return Item::create([
            'item_name' => $name, 'is_active' => true, 'default_touch' => 92.00, 'item_touch' => 90.00,
            'is_need_fitem_shown' => false, 'is_barcode' => false, 'is_no_barcode' => true,
        ]);
    }

    private function payload(int $roleId, string $suffix, array $extra = []): array
    {
        return array_merge([
            'name' => "New {$suffix}",
            'user_name' => "new_{$suffix}",
            'password' => 'secret123',
            'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'phone_no' => '95' . str_pad((string) (crc32($suffix) % 100000000), 8, '0', STR_PAD_LEFT),
            'role_id' => $roleId,
            'mailing_name' => "New {$suffix}",
            'category_name' => 'PURITY',
            'system_id' => "SYS_NEW_{$suffix}",
        ], $extra);
    }

    /** The minimum update() needs alongside a change. */
    private function editBase(UserDetail $user): array
    {
        return [
            'name' => $user->name,
            'user_name' => $user->user_name,
            'address' => 'Test',
            'phone_no' => $user->phone_no,
            'category_name' => 'BOTH',
        ];
    }

    public function test_the_console_reads_each_roles_profile(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $data = $this->getJson('/api/v1/admin/user-form-profiles')->assertOk()->json('data');

        $admin = $data['by_role_id'][(string) $this->adminRole->id];
        $this->assertFalse($admin['is_default']);
        $this->assertSame('ADMIN', $admin['role']);
        $this->assertSame(
            ['customer_comments', 'heads', 'items', 'balances', 'customer_touches', 'image', 'joining_date', 'flags'],
            $admin['sections'],
        );
        $this->assertTrue($admin['lock_role_on_edit']);
        $this->assertSame([
            'is_delivery_item_shown', 'is_delivery_chk_need_shown', 'is_wa_delivery_stock_needed',
            'is_cashamt_thermal_shown', 'is_customer_touch_need_shown', 'is_remainder_shown',
        ], $admin['flags']);

        // HEAD: the legacy head user form; its role can change on edit.
        $head = $data['by_role_id'][(string) $this->headRole->id];
        $this->assertFalse($head['is_default']);
        $this->assertFalse($head['lock_role_on_edit']);
        $this->assertSame(
            ['head_users', 'items', 'balances', 'image', 'profile_image', 'proof', 'salary', 'joining_date', 'report_password', 'flags'],
            $head['sections'],
        );
        $this->assertCount(15, $head['flags']);
        $this->assertContains('is_item_change_need_shown', $head['flags']);
        $this->assertContains('is_phone_book_need_shown', $head['flags']);

        // A role without its own profile keeps the full form.
        $employee = $data['by_role_id'][(string) $this->employeeRole->id];
        $this->assertTrue($employee['is_default']);
        $this->assertSame($data['sections'], $employee['sections']);
        $this->assertSame($data['flags'], $employee['flags']);
    }

    public function test_an_admin_role_user_gets_only_what_its_profile_allows(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        // Flags outside the profile are ignored whichever way they're sent;
        // the backend's defaults apply, as for any create that omits them
        // (the three calculation engines default on, everything else off —
        // the same as the column defaults, so as the legacy form did).
        $this->postJson('/api/v1/admin/user-details', $this->payload($this->adminRole->id, 'adm', [
            'is_remainder_shown' => true,      // in the ADMIN profile
            'is_gold_cal_enabled' => false,    // not in it (default on)
            'is_act_as_head_enabled' => true,  // not in it (default off)
            'remarks' => 'dropped',           // section not in it
            // A section the profile leaves out, so it's dropped — and doesn't
            // ask for the special password either.
            'cash_head_mappings' => [['head_id' => $this->head->user_id]],
        ]))->assertStatus(201);

        $user = UserDetail::where('user_name', 'new_adm')->firstOrFail();
        // Not every flag has a boolean cast on the model, hence (bool).
        $this->assertTrue((bool) $user->is_remainder_shown);
        $this->assertTrue((bool) $user->is_gold_cal_enabled);
        $this->assertFalse((bool) $user->is_act_as_head_enabled);
        $this->assertNull($user->remarks);
        $this->assertFalse(CashHeadEmployeeMapping::where('employee_id', $user->user_id)->exists());

        // Editing enforces the same profile: a flag outside it stays as it was.
        $this->putJson('/api/v1/admin/user-details/' . $user->user_id, $this->editBase($user) + [
            'is_gold_cal_enabled' => false,
            'is_act_as_head_enabled' => true,
            'is_delivery_item_shown' => true,
        ])->assertOk();
        $user->refresh();
        $this->assertTrue((bool) $user->is_gold_cal_enabled);
        $this->assertFalse((bool) $user->is_act_as_head_enabled);
        $this->assertTrue((bool) $user->is_delivery_item_shown);
    }

    public function test_roles_without_a_profile_keep_the_full_form(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/user-details', $this->payload($this->employeeRole->id, 'emp', [
            'is_gold_cal_enabled' => true,
            'remarks' => 'kept',
        ]))->assertStatus(201);

        $user = UserDetail::where('user_name', 'new_emp')->firstOrFail();
        $this->assertTrue((bool) $user->is_gold_cal_enabled);
        $this->assertSame('kept', $user->remarks);
    }

    public function test_joining_date_and_customer_touches_are_saved_with_the_user(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/user-details', $this->payload($this->adminRole->id, 'ct', [
            'joining_date' => '2026-11-01',
            'customer_touch_mappings' => [
                ['customer_touch_id' => $this->touch91->item_id, 'is_active' => true],
                ['customer_touch_id' => $this->touch92->item_id, 'is_active' => false],
            ],
        ]))->assertStatus(201);
        $user = UserDetail::where('user_name', 'new_ct')->firstOrFail();

        $shown = $this->getJson('/api/v1/admin/user-details/' . $user->user_id)->assertOk()->json('data.user');
        $this->assertSame('2026-11-01', $shown['joining_date']);
        $this->assertCount(2, $shown['customer_touch_mappings']);

        // Edit replaces the set: one dropped, one flipped active.
        $this->putJson('/api/v1/admin/user-details/' . $user->user_id, $this->editBase($user) + [
            'customer_touch_mappings' => [['customer_touch_id' => $this->touch92->item_id, 'is_active' => true]],
        ])->assertOk();
        $this->assertSame(
            [[$this->touch92->item_id, true]],
            CustomerTouchUserMapping::where('user_id', $user->user_id)->get()
                ->map(fn ($m) => [$m->customer_touch_id, $m->is_active])->all(),
        );

        // Unknown or repeated touches are a 422, not a 500.
        $this->putJson('/api/v1/admin/user-details/' . $user->user_id, $this->editBase($user) + [
            'customer_touch_mappings' => [['customer_touch_id' => 9999]],
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_touch_mappings.0.customer_touch_id']);
        $this->putJson('/api/v1/admin/user-details/' . $user->user_id, $this->editBase($user) + [
            'customer_touch_mappings' => [
                ['customer_touch_id' => $this->touch91->item_id],
                ['customer_touch_id' => $this->touch91->item_id],
            ],
        ])->assertStatus(422);
    }

    public function test_changing_heads_on_an_edit_needs_the_special_password(): void
    {
        HeadEmployeeMapping::create(['head_id' => $this->head->user_id, 'employee_id' => $this->employee->user_id, 'added_at' => now()]);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $this->employee->user_id;
        $base = $this->editBase($this->employee);

        // Heads not sent: no special password, heads untouched.
        $this->putJson($url, $base + ['name' => 'Renamed'])->assertOk();
        $this->assertTrue(HeadEmployeeMapping::where('employee_id', $this->employee->user_id)->where('head_id', $this->head->user_id)->exists());

        $swap = $base + ['head_mappings' => [['head_id' => $this->otherHead->user_id]]];
        $this->putJson($url, $swap)->assertStatus(422)->assertJsonValidationErrors(['special_password']);
        $this->putJson($url, $swap, $this->withSpecialPassword())->assertOk();
        $this->assertSame(
            [$this->otherHead->user_id],
            HeadEmployeeMapping::where('employee_id', $this->employee->user_id)->pluck('head_id')->map(fn ($id) => (int) $id)->all(),
        );

        // Clearing every head is a change too.
        $this->putJson($url, $base + ['head_mappings' => []])->assertStatus(422);
        $this->putJson($url, $base + ['head_mappings' => []], $this->withSpecialPassword())->assertOk();
        $this->assertFalse(HeadEmployeeMapping::where('employee_id', $this->employee->user_id)->exists());

        // An unknown head is a 422.
        $this->putJson($url, $base + ['head_mappings' => [['head_id' => 9999]]], $this->withSpecialPassword())
            ->assertStatus(422);
    }

    public function test_admins_choose_items_but_never_change_their_totals(): void
    {
        UsersItemsMapping::create([
            'user_id' => $this->employee->user_id, 'item_id' => $this->gold->item_id,
            'item_grams_total' => '25', 'item_purity_total' => '23', 'is_primary' => 1, 'added_at' => now(),
        ]);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $this->employee->user_id;
        $base = $this->editBase($this->employee);

        // Add Silver, flip Gold's primary; the totals sent are ignored.
        $this->putJson($url, $base + ['item_mappings' => [
            ['item_id' => $this->gold->item_id, 'is_primary' => 0, 'item_grams_total' => '999', 'item_purity_total' => '999'],
            ['item_id' => $this->silver->item_id, 'is_primary' => 1, 'item_grams_total' => '50', 'item_purity_total' => '50'],
        ]])->assertOk();

        $gold = UsersItemsMapping::where('user_id', $this->employee->user_id)->where('item_id', $this->gold->item_id)->firstOrFail();
        $silver = UsersItemsMapping::where('user_id', $this->employee->user_id)->where('item_id', $this->silver->item_id)->firstOrFail();
        $this->assertEquals(25, (float) $gold->item_grams_total);
        $this->assertEquals(23, (float) $gold->item_purity_total);
        $this->assertSame(0, (int) $gold->is_primary);
        $this->assertEquals(0, (float) $silver->item_grams_total);
        $this->assertSame(1, (int) $silver->is_primary);

        // Gold still holds a balance, so it can't be removed; Silver (zero) can.
        $this->putJson($url, $base + ['item_mappings' => [['item_id' => $this->silver->item_id, 'is_primary' => 1]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_mappings'])
            ->assertJsonPath('errors.item_mappings.0', "Gold still holds a balance for this user, so it can't be removed.");
        $this->assertTrue(UsersItemsMapping::where('user_id', $this->employee->user_id)->where('item_id', $this->gold->item_id)->exists());

        $this->putJson($url, $base + ['item_mappings' => [['item_id' => $this->gold->item_id, 'is_primary' => 1]]])->assertOk();
        $this->assertFalse(UsersItemsMapping::where('user_id', $this->employee->user_id)->where('item_id', $this->silver->item_id)->exists());
    }

    public function test_a_new_users_items_start_at_zero_when_an_admin_creates_them(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/user-details', $this->payload($this->employeeRole->id, 'itm', [
            'item_mappings' => [['item_id' => $this->gold->item_id, 'is_primary' => 1, 'item_grams_total' => '40', 'item_purity_total' => '38']],
        ]))->assertStatus(201);

        $user = UserDetail::where('user_name', 'new_itm')->firstOrFail();
        $row = UsersItemsMapping::where('user_id', $user->user_id)->firstOrFail();
        $this->assertEquals(0, (float) $row->item_grams_total);
        $this->assertEquals(0, (float) $row->item_purity_total);
    }

    public function test_admins_see_balances_read_only_and_non_heads_still_cannot(): void
    {
        $this->employee->forceFill(['grams_grand_total' => 12.5, 'rak_cash_balance' => 700])->save();
        UsersItemsMapping::create([
            'user_id' => $this->employee->user_id, 'item_id' => $this->gold->item_id,
            'item_grams_total' => '12.5', 'item_purity_total' => '11', 'is_primary' => 1, 'added_at' => now(),
        ]);

        $this->signInToAdminConsoleAs($this->admin);
        $shown = $this->getJson('/api/v1/admin/user-details/' . $this->employee->user_id)->assertOk()->json('data.user');
        $this->assertEquals(12.5, (float) $shown['grams_grand_total']);
        $this->assertEquals(700, (float) $shown['rak_cash_balance']);
        $this->assertEquals(12.5, (float) $shown['item_mappings'][0]['item_grams_total']);

        // In the app, another non-head sees none of it — gm/purity included,
        // which show() used to add after the model had hidden the rest.
        $this->app['auth']->forgetGuards();
        $other = $this->makeUser('employee_b', $this->employeeRole->id, '9400000004');
        Passport::actingAs(AuthUser::findOrFail($other->user_id));
        $this->getJson('/api/v1/user-details/' . $this->employee->user_id)->assertOk()
            ->assertJsonMissingPath('data.user.grams_grand_total')
            ->assertJsonMissingPath('data.user.gm')
            ->assertJsonMissingPath('data.user.purity')
            ->assertJsonMissingPath('data.user.item_mappings.0.item_grams_total');
    }

    public function test_the_users_list_toggles_status_and_carries_the_list_columns(): void
    {
        $this->employee->forceFill(['is_salary_person' => true, 'per_day_salary' => 650])->save();
        $this->signInToAdminConsoleAs($this->admin);

        $row = collect($this->getJson('/api/v1/admin/user-details')->assertOk()->json('data'))
            ->firstWhere('id', $this->employee->user_id);
        $this->assertSame('employee_a', $row['user_name']);
        $this->assertSame($this->employeeRole->id, $row['role_id']);
        $this->assertTrue($row['is_salary_person']);
        $this->assertEquals(650, (float) $row['per_day_salary']);

        $url = '/api/v1/admin/user-details/' . $this->employee->user_id . '/status';
        $this->patchJson($url, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($this->employee->fresh()->is_active);
        $this->patchJson($url, [])->assertStatus(422);
        $this->patchJson('/api/v1/admin/user-details/9999/status', ['is_active' => true])->assertStatus(404);

        $this->assertTrue(SystemLog::where('action', 'USER_STATUS_CHANGE')->where('admin_id', $this->admin->id)->exists());
    }
}
