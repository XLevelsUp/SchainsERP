<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Support\AdminPasswordPolicy;
use Illuminate\Console\Command;

/**
 * Recovery for a forgotten admin password or special password. The console
 * can change either one only with the current value, so a forgotten one is
 * reset here on the server. Signs the admin out everywhere.
 *
 *   php artisan admin:reset-password admin            login password
 *   php artisan admin:reset-password admin --special  special password
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password
        {user_name : The admin to reset}
        {--special : Reset the special password instead of the login password}';

    protected $description = "Reset an admin console account's login or special password";

    public function handle(): int
    {
        $admin = Admin::where('user_name', (string) $this->argument('user_name'))->first();
        if (!$admin) {
            $this->error('No admin with that user name.');
            return self::FAILURE;
        }

        $special = (bool) $this->option('special');
        $label = $special ? 'New special password' : 'New login password';
        $min = $special ? AdminPasswordPolicy::SPECIAL_PASSWORD_MIN : AdminPasswordPolicy::PASSWORD_MIN;

        $value = (string) $this->secret($label);
        if (strlen($value) < $min) {
            $this->error("At least {$min} characters.");
            return self::FAILURE;
        }
        if ((string) $this->secret("{$label} (again)") !== $value) {
            $this->error("The two entries don't match.");
            return self::FAILURE;
        }

        $clashes = $special
            ? AdminPasswordPolicy::checkPassword($admin, $value)
            : AdminPasswordPolicy::checkSpecialPassword($admin, $value);
        if ($clashes) {
            $this->error('The login password and the special password must be different.');
            return self::FAILURE;
        }

        $special
            ? AdminPasswordPolicy::setSpecialPassword($admin, $value)
            : AdminPasswordPolicy::setPassword($admin, $value);

        $revoked = $admin->tokens()->where('revoked', false)->update(['revoked' => true]);

        $this->info(($special ? 'Special' : 'Login') . " password reset for \"{$admin->user_name}\"; {$revoked} open session(s) signed out.");

        return self::SUCCESS;
    }
}
