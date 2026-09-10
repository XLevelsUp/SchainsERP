<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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

        \App\Models\StockDetails::observe(\App\Observers\StockDetailsObserver::class);
        \App\Models\StockInDetail::observe(\App\Observers\StockInDetailObserver::class);
    }
}
