<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Support\AdminPasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates an admin console account. There is deliberately no screen for
 * this: admins manage users, so whoever runs the server creates admins.
 *
 *   php artisan admin:create admin --name="Office Admin"
 *
 * Both passwords are asked for interactively (hidden, typed twice) so they
 * never land in shell history.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {user_name : Login name for the admin console}
        {--name= : Display name (defaults to the user name)}';

    protected $description = 'Create an admin console account (login password + special password)';

    public function handle(): int
    {
        $userName = trim((string) $this->argument('user_name'));

        if ($userName === '' || strlen($userName) > 50) {
            $this->error('The user name must be 1–50 characters.');
            return self::FAILURE;
        }
        if (Admin::where('user_name', $userName)->exists()) {
            $this->error("An admin named \"{$userName}\" already exists. Use admin:reset-password to change its passwords.");
            return self::FAILURE;
        }

        $password = $this->askSecret('Login password', AdminPasswordPolicy::PASSWORD_MIN);
        if ($password === null) {
            return self::FAILURE;
        }

        $special = $this->askSecret('Special password (asked again for sensitive changes)', AdminPasswordPolicy::SPECIAL_PASSWORD_MIN);
        if ($special === null) {
            return self::FAILURE;
        }
        if ($special === $password) {
            $this->error('The special password must be different from the login password.');
            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $this->option('name') ?: $userName,
            'user_name' => $userName,
            'password_hash' => Hash::make($password),
            'special_password_hash' => Hash::make($special),
            'is_active' => true,
            'password_changed_at' => now(),
            'special_password_changed_at' => now(),
        ]);

        $this->info("Admin \"{$admin->user_name}\" created (id {$admin->id}). Sign in at /admin/login.");

        return self::SUCCESS;
    }

    private function askSecret(string $label, int $min): ?string
    {
        $value = (string) $this->secret($label);
        if (strlen($value) < $min) {
            $this->error("{$label}: at least {$min} characters.");
            return null;
        }
        if ((string) $this->secret("{$label} (again)") !== $value) {
            $this->error("{$label}: the two entries don't match.");
            return null;
        }
        return $value;
    }
}
