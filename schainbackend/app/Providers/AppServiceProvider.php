<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \App\Models\StockDetails::observe(\App\Observers\StockDetailsObserver::class);
        \App\Models\StockInDetail::observe(\App\Observers\StockInDetailObserver::class);
    }
}
