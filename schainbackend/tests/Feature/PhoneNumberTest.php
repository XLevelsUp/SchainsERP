<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * Users' phone numbers (agreed 2026-11-23): exactly 10 digits, a leading 0
 * allowed, and no two users may share one. Applies to the admin console's
 * create and edit and to the app's Add Retailer; an older number of another
 * length has to be corrected the next time that user is saved.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    private const LENGTH_ERROR = 'Phone number must be exactly 10 digits.';
    private const TAKEN_ERROR = 'Another user already has this phone number.';

    protected Role $headRole;
    protected Role $employeeRole;
    protected Role $customerRole;
    protected UserDetail $head;
    protected UserDetail $employee;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->employeeRole = Role::create(['role' => 'EMPLOYEE']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);

        $this->head = $this->makeUser('head_a', $this->headRole->id, '9500000001');
        $this->employee = $this->makeUser('employee_a', $this->employeeRole->id, '9500000002');
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

    private function payload(string $suffix, string $phone, ?int $roleId = null, array $extra = []): array
    {
        return array_merge([
            'name' => "New {$suffix}",
            'user_name' => "new_{$suffix}",
            'password' => 'secret123',
            'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'phone_no' => $phone,
            'role_id' => $roleId ?? $this->employeeRole->id,
            'mailing_name' => "New {$suffix}",
            'category_name' => 'BOTH',
            'system_id' => "SYS_NEW_{$suffix}",
        ], $extra);
    }

    private function editPayload(UserDetail $user, string $phone): array
    {
        return [
            'name' => $user->name,
            'user_name' => $user->user_name,
            'address' => 'Test',
            'phone_no' => $phone,
            'category_name' => 'BOTH',
        ];
    }

    private function assertPhoneError($response, string $message): void
    {
        $response->assertStatus(422)->assertJsonPath('errors.phone_no.0', $message);
    }

    public function test_the_console_accepts_only_ten_digits_and_keeps_a_leading_zero(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        foreach (['950000003', '95000000031', '95000 0003', '+919500003', 'abcdefghij', '95000000.3'] as $i => $bad) {
            $this->assertPhoneError($this->postJson('/api/v1/admin/user-details', $this->payload("bad{$i}", $bad)), self::LENGTH_ERROR);
        }
        $this->postJson('/api/v1/admin/user-details', $this->payload('empty', ''))->assertStatus(422)
            ->assertJsonValidationErrors(['phone_no']);
        $this->assertSame(2, UserDetail::count());

        $this->postJson('/api/v1/admin/user-details', $this->payload('zero', '0123456789'))->assertStatus(201);
        $this->assertSame('0123456789', UserDetail::where('user_name', 'new_zero')->value('phone_no'));
    }

    public function test_no_two_users_share_a_number(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->assertPhoneError(
            $this->postJson('/api/v1/admin/user-details', $this->payload('dup', $this->employee->phone_no)),
            self::TAKEN_ERROR,
        );

        // Editing a user onto someone else's number is refused too; keeping
        // their own number is fine.
        $this->assertPhoneError(
            $this->putJson("/api/v1/admin/user-details/{$this->employee->user_id}", $this->editPayload($this->employee, $this->head->phone_no)),
            self::TAKEN_ERROR,
        );
        $this->putJson("/api/v1/admin/user-details/{$this->employee->user_id}", $this->editPayload($this->employee, $this->employee->phone_no))
            ->assertOk();
        $this->assertSame('9500000002', $this->employee->fresh()->phone_no);
    }

    public function test_an_older_number_of_another_length_must_be_fixed_on_the_next_save(): void
    {
        $legacy = $this->makeUser('legacy_a', $this->employeeRole->id, '95000000099');
        $this->signInToAdminConsoleAs($this->admin);

        $this->assertPhoneError(
            $this->putJson("/api/v1/admin/user-details/{$legacy->user_id}", $this->editPayload($legacy, $legacy->phone_no)),
            self::LENGTH_ERROR,
        );
        $this->putJson("/api/v1/admin/user-details/{$legacy->user_id}", $this->editPayload($legacy, '9500000099'))
            ->assertOk();
        $this->assertSame('9500000099', $legacy->fresh()->phone_no);
    }

    public function test_add_retailer_follows_the_same_rule(): void
    {
        Passport::actingAs(AuthUser::findOrFail($this->head->user_id));
        $self = ['head_mappings' => [['head_id' => $this->head->user_id]]];

        $this->assertPhoneError(
            $this->postJson('/api/v1/user-details', $this->payload('r1', '95000001', $this->customerRole->id, $self)),
            self::LENGTH_ERROR,
        );
        $this->assertPhoneError(
            $this->postJson('/api/v1/user-details', $this->payload('r2', $this->employee->phone_no, $this->customerRole->id, $self)),
            self::TAKEN_ERROR,
        );
        $this->postJson('/api/v1/user-details', $this->payload('r3', '0950000003', $this->customerRole->id, $self))
            ->assertStatus(201);
        $this->assertSame('0950000003', UserDetail::where('user_name', 'new_r3')->value('phone_no'));
    }
}
