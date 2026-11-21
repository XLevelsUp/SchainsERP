<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminPasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdmins;
use Tests\TestCase;

/** php artisan admin:create / admin:reset-password */
class AdminCommandsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdmins;

    public function test_admin_create_asks_for_both_passwords_and_stores_them_hashed(): void
    {
        $this->artisan('admin:create', ['user_name' => 'office', '--name' => 'Office Admin'])
            ->expectsQuestion('Login password', 'login-pass-1')
            ->expectsQuestion('Login password (again)', 'login-pass-1')
            ->expectsQuestion('Special password (asked again for sensitive changes)', 'special-1')
            ->expectsQuestion('Special password (asked again for sensitive changes) (again)', 'special-1')
            ->assertSuccessful();

        $admin = Admin::where('user_name', 'office')->firstOrFail();
        $this->assertSame('Office Admin', $admin->name);
        $this->assertTrue(AdminPasswordPolicy::checkPassword($admin, 'login-pass-1'));
        $this->assertTrue(AdminPasswordPolicy::checkSpecialPassword($admin, 'special-1'));
        $this->assertNotSame('login-pass-1', $admin->password_hash);
    }

    public function test_admin_create_refuses_matching_passwords_and_duplicates(): void
    {
        $this->artisan('admin:create', ['user_name' => 'office'])
            ->expectsQuestion('Login password', 'same-pass-1')
            ->expectsQuestion('Login password (again)', 'same-pass-1')
            ->expectsQuestion('Special password (asked again for sensitive changes)', 'same-pass-1')
            ->expectsQuestion('Special password (asked again for sensitive changes) (again)', 'same-pass-1')
            ->assertFailed();
        $this->assertSame(0, Admin::count());

        $this->makeAdmin('taken');
        $this->artisan('admin:create', ['user_name' => 'taken'])->assertFailed();
    }

    public function test_reset_password_replaces_the_special_password_and_signs_out(): void
    {
        $admin = $this->makeAdmin('office');

        $this->artisan('admin:reset-password', ['user_name' => 'office', '--special' => true])
            ->expectsQuestion('New special password', 'fresh-special')
            ->expectsQuestion('New special password (again)', 'fresh-special')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(AdminPasswordPolicy::checkSpecialPassword($admin, 'fresh-special'));
        $this->assertFalse(AdminPasswordPolicy::checkSpecialPassword($admin, $this->adminSpecialPassword));
        $this->assertTrue(AdminPasswordPolicy::checkPassword($admin, $this->adminPassword));
    }
}
