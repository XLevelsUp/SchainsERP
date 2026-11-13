<?php

namespace Tests\Feature;

use App\Models\UserDetail;
use Database\Seeders\UserDetailSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * BACKEND_TODO §5: seeded accounts must never get the literal password
 * "password" again.
 */
class SeedPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function resetGeneratedPassword(): void
    {
        // SeedPassword caches one password per process; clear it between tests.
        $property = new \ReflectionProperty(\Database\Seeders\Support\SeedPassword::class, 'plain');
        $property->setValue(null, null);
    }

    public function test_seeded_accounts_use_the_configured_password(): void
    {
        $this->resetGeneratedPassword();
        config(['app.seed_user_password' => 'Configured-Secret-42']);

        $this->seed(UserDetailSeeder::class);

        $hash = UserDetail::where('user_name', 'head_admin')->value('password_hash');
        $this->assertTrue(Hash::check('Configured-Secret-42', $hash));
        $this->assertFalse(Hash::check('password', $hash));
    }

    public function test_without_a_configured_password_a_random_one_is_used(): void
    {
        $this->resetGeneratedPassword();
        config(['app.seed_user_password' => null]);

        $this->seed(UserDetailSeeder::class);

        foreach (UserDetail::pluck('password_hash') as $hash) {
            $this->assertFalse(Hash::check('password', $hash));
        }
    }
}
