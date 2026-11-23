<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CashHeadEmployeeMapping;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The CUSTOMER user form (2026-11-23, the legacy customer form). Customers
 * are records for tracking gold and cash, not accounts: the form has no Name
 * or Password, the name follows the user name and a new customer gets a
 * random password. New customers start on category PURITY with GOLD as the
 * primary item (form defaults the console reads from the profile).
 */
class AdminCustomerFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $customerRole;
    protected Role $headRole;
    protected UserDetail $head;
    protected Admin $admin;

    private const FLAGS = [
        'is_remainder_shown', 'is_wa_delivery_stock_needed', 'is_delivery_item_shown',
        'is_delivery_chk_need_shown', 'is_cashamt_thermal_shown', 'is_customer_touch_need_shown',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);
        $this->head = UserDetail::create([
            'name' => 'Head A', 'user_name' => 'head_a', 'password_hash' => 'dummy',
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => '9700000001', 'proff' => '-',
            'role_id' => $this->headRole->id, 'mailing_name' => 'head_a', 'category_name' => 'BOTH', 'system_id' => 'SYS_HEAD_A',
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
        $this->admin = $this->makeAdmin();
    }

    private function customerPayload(array $extra = []): array
    {
        return array_merge([
            'user_name' => 'Mahalaxmi N',
            'phone_no' => '0970000002',
            'role_id' => $this->customerRole->id,
            'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => 'Mahalaxmi N',
            'category_name' => 'PURITY',
            'system_id' => 'SYS_MAHALAXMI',
        ], $extra);
    }

    public function test_the_console_reads_the_customer_profile(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $customer = $this->getJson('/api/v1/admin/user-form-profiles')->assertOk()
            ->json('data.by_role_id.' . $this->customerRole->id);

        $this->assertFalse($customer['is_default']);
        $this->assertFalse($customer['signs_in']);
        $this->assertSame('PURITY', $customer['default_category']);
        $this->assertSame('GOLD', $customer['default_primary_item']);
        $this->assertSame(
            ['customer_comments', 'heads', 'items', 'balances', 'customer_touches', 'joining_date', 'flags'],
            $customer['sections'],
        );
        $this->assertEqualsCanonicalizing(self::FLAGS, $customer['flags']);

        // Other roles still sign in and have no defaults.
        $head = $this->getJson('/api/v1/admin/user-form-profiles')->json('data.by_role_id.' . $this->headRole->id);
        $this->assertTrue($head['signs_in']);
        $this->assertNull($head['default_category']);
        $this->assertNull($head['default_primary_item']);
    }

    public function test_a_customer_is_created_without_a_name_or_password(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->postJson('/api/v1/admin/user-details', $this->customerPayload([
            'name' => 'Ignored',
            'password' => 'known-pass', // dropped: customers don't sign in
            'is_remainder_shown' => true,
            'is_act_as_head_enabled' => true,                                  // dropped: not a customer flag
            'cash_head_mappings' => [['head_id' => $this->head->user_id]],    // dropped: no cash heads
            'head_mappings' => [['head_id' => $this->head->user_id]],
            'customer_commants' => 'Pays monthly',
            'joining_date' => '2026-11-23',
        ]), $this->withSpecialPassword())->assertStatus(201);

        $customer = UserDetail::where('user_name', 'Mahalaxmi N')->firstOrFail();
        $this->assertSame('Mahalaxmi N', $customer->name);
        $this->assertNotEmpty($customer->password_hash);
        $this->assertFalse(Hash::check('known-pass', $customer->password_hash));
        $this->assertSame('PURITY', $customer->category_name);
        $this->assertTrue((bool) $customer->is_remainder_shown);
        $this->assertFalse((bool) $customer->is_act_as_head_enabled);
        $this->assertSame('Pays monthly', $customer->customer_commants);
        $this->assertFalse(CashHeadEmployeeMapping::where('employee_id', $customer->user_id)->exists());

        // The random password doesn't sign in either.
        $this->postJson('/api/v1/login', ['user_name' => 'Mahalaxmi N', 'password' => 'known-pass'])->assertStatus(401);
    }

    public function test_the_name_follows_the_user_name_only_when_it_changes(): void
    {
        $customer = UserDetail::create([
            'name' => 'Mahalaxmi N', 'user_name' => 'customer-1', 'password_hash' => Hash::make('old-pass'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => '9700000003', 'proff' => '-',
            'role_id' => $this->customerRole->id, 'mailing_name' => 'customer-1', 'category_name' => 'BOTH', 'system_id' => 'SYS_C1',
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ]);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $customer->user_id;
        $base = ['user_name' => 'customer-1', 'address' => 'Test', 'phone_no' => '9700000003', 'category_name' => 'GRAMS'];

        // Another edit keeps the saved name and password.
        $this->putJson($url, $base + ['name' => 'Ignored', 'password' => 'new-pass-1'])->assertOk();
        $customer->refresh();
        $this->assertSame('Mahalaxmi N', $customer->name);
        $this->assertSame('GRAMS', $customer->category_name);
        $this->assertTrue(Hash::check('old-pass', $customer->password_hash));

        // Renaming the user name renames the customer.
        $this->putJson($url, ['user_name' => 'Mahalaxmi Jewels'] + $base)->assertOk();
        $this->assertSame('Mahalaxmi Jewels', $customer->fresh()->name);
    }
}
