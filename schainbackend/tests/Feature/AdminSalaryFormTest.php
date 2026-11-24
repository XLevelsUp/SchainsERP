<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The SALARY user form (2026-11-23, the legacy salary form). Salary people
 * don't sign in (no Name or Password, like customers). The form has an
 * Additional Phone No: optional, 10 digits, and no number may belong to two
 * users in either phone column. New salary users start on GRAMS, GOLD as the
 * primary item, salary person YES and today's joining date (form defaults).
 */
class AdminSalaryFormTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $salaryRole;
    protected Role $headRole;
    protected UserDetail $head;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->salaryRole = Role::create(['role' => 'SALARY']);
        $this->head = $this->makeUser('head_a', '9700000001', $this->headRole, ['additional_phone_no' => '9700000099']);
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, string $phone, Role $role, array $extra = []): UserDetail
    {
        return UserDetail::create(array_merge([
            'name' => $userName, 'user_name' => $userName, 'password_hash' => Hash::make('old-pass'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $role->id, 'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ], $extra));
    }

    private function salaryPayload(array $extra = []): array
    {
        return array_merge([
            'user_name' => 'Ravi K',
            'phone_no' => '0970000002',
            'role_id' => $this->salaryRole->id,
            'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => 'Ravi K',
            'category_name' => 'GRAMS',
            'system_id' => 'SYS_RAVI',
        ], $extra);
    }

    public function test_the_console_reads_the_salary_profile(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $profiles = $this->getJson('/api/v1/admin/user-form-profiles')->assertOk();
        $salary = $profiles->json('data.by_role_id.' . $this->salaryRole->id);

        $this->assertFalse($salary['is_default']);
        $this->assertFalse($salary['signs_in']);
        $this->assertSame('GRAMS', $salary['default_category']);
        $this->assertSame('GOLD', $salary['default_primary_item']);
        $this->assertSame(['is_salary_person'], $salary['default_flags']);
        $this->assertTrue($salary['joining_date_defaults_to_today']);
        $this->assertSame(
            ['additional_phone', 'heads', 'items', 'balances', 'image', 'profile_image', 'proof', 'salary', 'joining_date'],
            $salary['sections'],
        );
        $this->assertSame(['is_salary_person'], $salary['flags']);

        // HEAD keeps its own settings and has no additional phone.
        $head = $profiles->json('data.by_role_id.' . $this->headRole->id);
        $this->assertSame([], $head['default_flags']);
        $this->assertFalse($head['joining_date_defaults_to_today']);
        $this->assertNotContains('additional_phone', $head['sections']);
    }

    public function test_a_salary_person_is_created_with_the_legacy_fields(): void
    {
        Storage::fake('public');
        $this->signInToAdminConsoleAs($this->admin);

        $this->post('/api/v1/admin/user-details', $this->salaryPayload([
            'name' => 'Ignored',
            'password' => 'known-pass',          // dropped: salary people don't sign in
            'additional_phone_no' => '0970000003',
            'head_mappings' => [['head_id' => $this->head->user_id]],
            'aadhar_number' => '123412341234',
            'is_salary_person' => '1',
            'per_day_salary' => '650',
            'joining_date' => '2026-11-23',
            'customer_commants' => 'dropped',    // not on the salary form
            'is_remainder_shown' => '1',         // not a salary permission
            'profile_image' => UploadedFile::fake()->image('me.png'),
            'image_url' => UploadedFile::fake()->image('logo.png'),
            'aadhar_image' => UploadedFile::fake()->image('front.png'),
            'aadhar_back_image' => UploadedFile::fake()->image('back.png'),
        ]), ['Accept' => 'application/json'] + $this->withSpecialPassword())->assertStatus(201);

        $user = UserDetail::where('user_name', 'Ravi K')->firstOrFail();
        $this->assertSame('Ravi K', $user->name);
        $this->assertFalse(Hash::check('known-pass', $user->password_hash));
        $this->assertSame('0970000003', $user->additional_phone_no);
        $this->assertSame('GRAMS', $user->category_name);
        $this->assertSame('123412341234', $user->aadhar_number);
        $this->assertTrue((bool) $user->is_salary_person);
        $this->assertEquals(650, $user->per_day_salary);
        $this->assertNull($user->customer_commants);
        $this->assertFalse((bool) $user->is_remainder_shown);
        foreach (['profile_image', 'image_url', 'aadhar_image', 'aadhar_back_image'] as $field) {
            $this->assertNotNull($user->{$field}, $field);
        }

        // Shown back on the form.
        $shown = $this->getJson('/api/v1/admin/user-details/' . $user->user_id)->assertOk()->json('data.user');
        $this->assertSame('0970000003', $shown['additional_phone_no']);

        $this->postJson('/api/v1/login', ['user_name' => 'Ravi K', 'password' => 'known-pass'])->assertStatus(401);
    }

    public function test_the_additional_phone_is_optional_ten_digits_and_unique_across_both_phones(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $errorFor = fn (array $extra) => $this->postJson('/api/v1/admin/user-details', $this->salaryPayload($extra))
            ->assertStatus(422)->json('errors')['additional_phone_no'][0] ?? null;

        $this->assertSame('Additional phone number must be exactly 10 digits.', $errorFor(['additional_phone_no' => '97000']));
        $this->assertSame('Another user already has this phone number.', $errorFor(['additional_phone_no' => '9700000001'])); // the head's main phone
        $this->assertSame('Another user already has this phone number.', $errorFor(['additional_phone_no' => '9700000099'])); // the head's additional phone
        $this->assertSame('Additional phone number must differ from the phone number.', $errorFor(['additional_phone_no' => '0970000002']));

        // A main phone can't be another user's additional phone either.
        $this->postJson('/api/v1/admin/user-details', $this->salaryPayload(['phone_no' => '9700000099']))
            ->assertStatus(422)->assertJsonValidationErrors(['phone_no']);

        // Left empty is fine.
        $this->postJson('/api/v1/admin/user-details', $this->salaryPayload(['additional_phone_no' => '']))->assertStatus(201);
        $this->assertNull(UserDetail::where('user_name', 'Ravi K')->value('additional_phone_no'));
    }

    public function test_editing_keeps_the_own_numbers_and_checks_against_the_saved_phone(): void
    {
        $user = $this->makeUser('salary-1', '9700000004', $this->salaryRole, ['additional_phone_no' => '9700000005']);
        $this->signInToAdminConsoleAs($this->admin);
        $url = '/api/v1/admin/user-details/' . $user->user_id;

        // Saving its own numbers again is fine.
        $this->putJson($url, ['user_name' => 'salary-1', 'phone_no' => '9700000004', 'additional_phone_no' => '9700000005'])->assertOk();

        // Without phone_no in the request, the saved phone is the one compared.
        $this->putJson($url, ['additional_phone_no' => '9700000004'])
            ->assertStatus(422)->assertJsonValidationErrors(['additional_phone_no']);

        // Cleared.
        $this->putJson($url, ['additional_phone_no' => ''])->assertOk();
        $this->assertNull($user->fresh()->additional_phone_no);
    }

    public function test_roles_without_the_section_cannot_set_an_additional_phone(): void
    {
        $this->signInToAdminConsoleAs($this->admin);

        $this->putJson('/api/v1/admin/user-details/' . $this->head->user_id, ['additional_phone_no' => '9700000077'])->assertOk();
        $this->assertSame('9700000099', $this->head->fresh()->additional_phone_no);
    }
}
