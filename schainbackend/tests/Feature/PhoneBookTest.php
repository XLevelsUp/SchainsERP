<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuthUser;
use App\Models\PhoneBook;
use App\Models\Role;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/**
 * The phone book (2026-11-24): its own list of contacts, managed in the
 * admin console and read in the app. Users the console creates are added;
 * nothing done to a contact changes a user.
 */
class PhoneBookTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    protected Role $headRole;
    protected Role $customerRole;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headRole = Role::create(['role' => 'HEAD']);
        $this->customerRole = Role::create(['role' => 'CUSTOMER']);
        $this->admin = $this->makeAdmin();
    }

    private function makeUser(string $userName, string $phone, Role $role, array $extra = []): UserDetail
    {
        return UserDetail::create(array_merge([
            'name' => strtoupper($userName), 'user_name' => $userName, 'password_hash' => Hash::make('pass-1234'),
            'address' => 'Test', 'signature' => '-', 'code' => '-', 'phone_no' => $phone, 'proff' => '-',
            'role_id' => $role->id, 'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
            'is_customerfitem_cal_enabled' => false, 'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true, 'is_delete' => false, 'is_billable' => true,
        ], $extra));
    }

    public function test_an_admin_creates_edits_and_deletes_a_contact_without_touching_users(): void
    {
        $user = $this->makeUser('satheesh', '8778480227', $this->headRole);
        $this->signInToAdminConsoleAs($this->admin);

        $id = $this->postJson('/api/v1/admin/phone-book', [
            'name' => 'PANNEER', 'phone_no' => '9942172556', 'role_id' => $this->customerRole->id, 'is_active' => false,
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'PANNEER')
            ->assertJsonPath('data.role.role_name', 'CUSTOMER')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.user_id', null)
            ->json('data.id');

        $this->putJson("/api/v1/admin/phone-book/{$id}", ['name' => 'PANNEER K', 'phone_no' => '9942172557', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.name', 'PANNEER K')
            ->assertJsonPath('data.phone_no', '9942172557')
            ->assertJsonPath('data.is_active', true);

        // A contact linked to a user: editing and deleting it leaves the user alone.
        $linked = PhoneBook::create(['name' => 'SATHEESH', 'phone_no' => '8778480227', 'role_id' => $this->headRole->id, 'user_id' => $user->user_id]);
        $this->putJson("/api/v1/admin/phone-book/{$linked->id}", ['name' => 'Satheesh R', 'phone_no' => '8778480228'])->assertOk();
        $this->deleteJson("/api/v1/admin/phone-book/{$linked->id}")->assertOk();
        $this->assertDatabaseMissing('phone_book', ['id' => $linked->id]);
        $user->refresh();
        $this->assertSame('SATHEESH', $user->name);
        $this->assertSame('8778480227', $user->phone_no);

        // Each change is in the admin audit log.
        $this->assertSame(
            ['PHONE_BOOK_CREATE', 'PHONE_BOOK_UPDATE', 'PHONE_BOOK_UPDATE', 'PHONE_BOOK_DELETE'],
            DB::table('system_logs')->where('reference_table', 'phone_book')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_a_contact_needs_a_name_a_unique_ten_digit_phone_and_a_role(): void
    {
        PhoneBook::create(['name' => 'GOWTHAM', 'phone_no' => '9942065135', 'role_id' => $this->customerRole->id]);
        $this->signInToAdminConsoleAs($this->admin);

        $errors = $this->postJson('/api/v1/admin/phone-book', ['phone_no' => '99420'])->assertStatus(422)->json('errors');
        $this->assertSame('Phone number must be exactly 10 digits.', $errors['phone_no'][0]);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('role_id', $errors);

        $this->postJson('/api/v1/admin/phone-book', ['name' => 'X', 'phone_no' => '9942065135', 'role_id' => $this->customerRole->id])
            ->assertStatus(422)->assertJsonPath('errors.phone_no.0', 'This phone number is already in the phone book.');
        $this->postJson('/api/v1/admin/phone-book', ['name' => 'X', 'phone_no' => '0942065135', 'role_id' => 999])
            ->assertStatus(422)->assertJsonPath('errors.role_id.0', 'Pick a role from the list.');

        // Saving a contact with its own number again is fine.
        $own = PhoneBook::where('phone_no', '9942065135')->value('id');
        $this->putJson("/api/v1/admin/phone-book/{$own}", ['name' => 'GOWTHAM', 'phone_no' => '9942065135'])->assertOk();

        $this->putJson('/api/v1/admin/phone-book/999999', ['name' => 'Nobody'])->assertNotFound();
    }

    public function test_the_list_filters_and_can_return_every_contact(): void
    {
        $user = $this->makeUser('raju', '9894620524', $this->headRole, ['profile_image' => 'profile_images/raju.png']);
        PhoneBook::create(['name' => 'RAJU BALLS', 'phone_no' => '9894620524', 'role_id' => $this->headRole->id, 'user_id' => $user->user_id]);
        PhoneBook::create(['name' => 'MANOJ KUMAR', 'phone_no' => '7812810299', 'role_id' => $this->customerRole->id]);
        PhoneBook::create(['name' => 'Karthik Central', 'phone_no' => '8973995573', 'role_id' => $this->customerRole->id, 'is_active' => false]);
        $this->signInToAdminConsoleAs($this->admin);

        $all = $this->getJson('/api/v1/admin/phone-book?all=1')->assertOk()->json('data');
        $this->assertSame(3, $all['total']);
        $this->assertCount(3, $all['data']);
        $this->assertSame(['Karthik Central', 'MANOJ KUMAR', 'RAJU BALLS'], array_column($all['data'], 'name'));
        $this->assertStringEndsWith('storage/profile_images/raju.png', $all['data'][2]['profile_image_url']);
        $this->assertNull($all['data'][1]['profile_image_url']);

        $names = fn (string $query) => array_column($this->getJson("/api/v1/admin/phone-book?all=1&{$query}")->json('data.data'), 'name');
        $this->assertSame(['Karthik Central'], $names('name=karthik'));
        $this->assertSame(['MANOJ KUMAR'], $names('phone_no=78128'));
        $this->assertSame(['RAJU BALLS'], $names('role_id=' . $this->headRole->id));
        $this->assertSame(['Karthik Central'], $names('is_active=0'));
        $this->assertSame(['RAJU BALLS', 'MANOJ KUMAR', 'Karthik Central'], $names('sort=-name'));

        $paged = $this->getJson('/api/v1/admin/phone-book?per_page=2&page=2')->json('data');
        $this->assertSame(2, $paged['last_page']);
        $this->assertCount(1, $paged['data']);
    }

    public function test_a_user_created_in_the_console_is_added_once(): void
    {
        $this->signInToAdminConsoleAs($this->admin);
        $payload = fn (string $userName, string $phone) => [
            'name' => strtoupper($userName), 'user_name' => $userName, 'password' => 'secret-12', 'phone_no' => $phone,
            'role_id' => $this->headRole->id, 'address' => '-', 'signature' => '-', 'code' => '-', 'proff' => '-',
            'mailing_name' => $userName, 'category_name' => 'BOTH', 'system_id' => 'SYS_' . $userName,
        ];

        $this->postJson('/api/v1/admin/user-details', $payload('dayal', '6380488924'))->assertStatus(201);
        $user = UserDetail::where('user_name', 'dayal')->firstOrFail();
        $contact = PhoneBook::where('phone_no', '6380488924')->firstOrFail();
        $this->assertSame('DAYAL', $contact->name);
        $this->assertSame($this->headRole->id, $contact->role_id);
        $this->assertSame($user->user_id, $contact->user_id);
        $this->assertTrue($contact->is_active);

        // A number already in the phone book is linked, not added again or renamed.
        PhoneBook::create(['name' => 'SHYAMAL CUTTING', 'phone_no' => '8436441572', 'role_id' => $this->customerRole->id]);
        $this->postJson('/api/v1/admin/user-details', $payload('shyamal', '8436441572'))->assertStatus(201);
        $this->assertSame(1, PhoneBook::where('phone_no', '8436441572')->count());
        $existing = PhoneBook::where('phone_no', '8436441572')->first();
        $this->assertSame('SHYAMAL CUTTING', $existing->name);
        $this->assertSame(UserDetail::where('user_name', 'shyamal')->value('user_id'), $existing->user_id);

        // Deleting the user keeps the contact and clears the link.
        $this->deleteJson('/api/v1/admin/user-details/' . $user->user_id, [], $this->withSpecialPassword())->assertOk();
        $this->assertNull(PhoneBook::where('phone_no', '6380488924')->value('user_id'));
    }

    public function test_the_app_reads_the_phone_book_and_cannot_change_it(): void
    {
        PhoneBook::create(['name' => 'BAPI HANDMADE', 'phone_no' => '9585334307', 'role_id' => $this->customerRole->id]);
        $head = $this->makeUser('head_pb', '9600000001', $this->headRole, ['is_phone_book_need_shown' => true]);
        Passport::actingAs(AuthUser::findOrFail($head->user_id));

        $row = $this->getJson('/api/v1/phone-book?search=bapi')->assertOk()->json('data.data.0');
        $this->assertSame('BAPI HANDMADE', $row['name']);
        $this->assertSame('CUSTOMER', $row['role']['role_name']);
        $this->getJson('/api/v1/phone-book/' . $row['id'])->assertOk()->assertJsonPath('data.phone_no', '9585334307');

        // The app has no write routes.
        $this->postJson('/api/v1/phone-book', ['name' => 'X', 'phone_no' => '9000000000', 'role_id' => $this->customerRole->id])->assertStatus(405);
        $this->assertSame(1, PhoneBook::count());
    }
}
