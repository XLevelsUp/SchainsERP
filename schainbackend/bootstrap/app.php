<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The admin console's API (admins create users and assign them to
        // heads), kept apart from the app's (App\Support\AdminAccess).
        // Route names get an `admin.` prefix so they never collide with the
        // app's identically named resources (items.index and so on).
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1/admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'act.as.head' => \App\Http\Middleware\EnsureCanActAsHead::class,
            'head.only' => \App\Http\Middleware\EnsureHead::class,
            // Admin console (routes/admin.php).
            'admin.session' => \App\Http\Middleware\EnsureAdminSession::class,
            'admin.audit' => \App\Http\Middleware\LogAdminAction::class,
            'special.password' => \App\Http\Middleware\RequireSpecialPassword::class,
            'user.profile' => \App\Http\Middleware\ApplyUserFormProfile::class,
            // App: the one user creation left there (Add Retailer).
            'quick.add.customer' => \App\Http\Middleware\EnsureQuickAddCustomer::class,
            // App: a feature the signed-in user's permissions don't allow.
            'user.permission' => \App\Http\Middleware\EnsureUserPermission::class,
            // App: ends the session of a user whose role was switched off.
            'app.session' => \App\Http\Middleware\EnsureAppSession::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => abort(response()->json([
            'success' => false,
            'message' => 'Unauthenticated.'
        ], 401)));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
