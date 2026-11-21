<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

/** Admin console accounts for feature tests. */
trait CreatesAdmins
{
    protected string $adminPassword = 'admin-login-pass';
    protected string $adminSpecialPassword = 'special-123';

    protected function makeAdmin(string $userName = 'admin_a', array $overrides = []): Admin
    {
        return Admin::create(array_merge([
            'name' => 'Admin ' . strtoupper($userName),
            'user_name' => $userName,
            'password_hash' => Hash::make($this->adminPassword),
            'special_password_hash' => Hash::make($this->adminSpecialPassword),
            'is_active' => true,
        ], $overrides));
    }

    /** An admin-console session (admin guard, `admin` scope) without a real token. */
    protected function signInToAdminConsoleAs(Admin $admin, array $scopes = [AdminAccess::SCOPE]): void
    {
        Passport::actingAs($admin, $scopes, 'admin');
    }

    /** Headers carrying the special password, for sensitive admin changes. */
    protected function withSpecialPassword(?string $password = null): array
    {
        return [AdminAccess::SPECIAL_PASSWORD_HEADER => $password ?? $this->adminSpecialPassword];
    }
}
