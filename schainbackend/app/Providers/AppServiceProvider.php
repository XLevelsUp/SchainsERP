<?php

namespace App\Providers;

use App\Support\AdminAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addHours(24));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addHours(24));

        // Admin console tokens carry this scope (App\Support\AdminAccess).
        // Passport refuses to issue a scope it hasn't been told about.
        Passport::tokensCan([
            AdminAccess::SCOPE => 'Administer SchainsERP: users, roles, items, settings and the audit log',
        ]);

        // POST /api/v1/admin/login: 5 tries a minute per username and IP,
        // 20 a minute per IP across usernames.
        RateLimiter::for('admin-login', function (Request $request) {
            $tooMany = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'message' => 'Too many sign-in attempts. Please wait a minute and try again.',
            ], 429, $headers);

            return [
                Limit::perMinute(5)
                    ->by('admin-login:' . Str::lower((string) $request->input('user_name')) . '|' . $request->ip())
                    ->response($tooMany),
                Limit::perMinute(20)
                    ->by('admin-login-ip:' . $request->ip())
                    ->response($tooMany),
            ];
        });

        \App\Models\StockDetails::observe(\App\Observers\StockDetailsObserver::class);
        \App\Models\StockInDetail::observe(\App\Observers\StockInDetailObserver::class);
    }
}
