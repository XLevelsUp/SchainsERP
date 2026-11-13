<?php

namespace Database\Seeders\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password for every account the seeders create (BACKEND_TODO §5).
 *
 * Uses SEED_USER_PASSWORD (config `app.seed_user_password`). When that is not
 * set, one random password is generated per seeding run and printed once, so
 * no environment ever gets a guessable default like "password" again.
 */
final class SeedPassword
{
    private static ?string $plain = null;

    public static function hash(?Command $command = null): string
    {
        if (self::$plain === null) {
            $configured = config('app.seed_user_password');

            if (is_string($configured) && $configured !== '') {
                self::$plain = $configured;
            } else {
                self::$plain = Str::password(20, symbols: false);
                $command?->warn(
                    'SEED_USER_PASSWORD is not set, so seeded accounts get this generated password: '
                    . self::$plain
                );
            }
        }

        return Hash::make(self::$plain);
    }
}
