<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Admin\AdminAccountController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\UserFormProfileController;
use App\Http\Controllers\Api\Admin\UserStatusController;
use App\Http\Controllers\Api\CashHeadEmployeeMappingController;
use App\Http\Controllers\Api\CustomerTouchController;
use App\Http\Controllers\Api\HeadEmployeeMappingController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserDetailController;

/*
|--------------------------------------------------------------------------
| Admin console API — /api/v1/admin/*
|--------------------------------------------------------------------------
| Mounted in bootstrap/app.php with the `api` middleware group, the
| /api/v1/admin prefix and `admin.` route names.
|
| Admins are their own accounts (table `admins`), not heads. Their job
| (agreed 2026-11-20): create users, assign them to heads, and create
| roles. Heads keep everything else in the app (routes/api.php).
|
| Past login, every route needs an admin-console token (`auth:admin` +
| EnsureAdminSession) and successful changes are audited (LogAdminAction).
| Assigning heads and deleting a user also need the admin's special
| password on the request (RequireSpecialPassword).
|--------------------------------------------------------------------------
*/

Route::post('login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('login');

Route::middleware(['auth:admin', 'admin.session', 'admin.audit'])->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
    Route::get('me', [AdminAuthController::class, 'me'])->name('me');

    // The admin's own account. Each needs the current value of the secret
    // being changed; throttled against guessing it.
    Route::middleware('throttle:6,1')->group(function () {
        Route::put('account/password', [AdminAccountController::class, 'updatePassword'])
            ->name('account.password');
        Route::put('account/special-password', [AdminAccountController::class, 'updateSpecialPassword'])
            ->name('account.special-password');
    });

    // Users. The form's sections and permissions depend on the user's role
    // (App\Support\UserFormProfiles): served by user-form-profiles, enforced
    // on save by user.profile. Sending heads (create, or an edit that changes
    // them) and deleting a user need the special password.
    Route::get('user-form-profiles', UserFormProfileController::class)->name('user-form-profiles');
    Route::get('user-details', [UserDetailController::class, 'index'])->name('user-details.index');
    Route::get('user-details/{user_detail}', [UserDetailController::class, 'show'])->name('user-details.show');
    Route::post('user-details', [UserDetailController::class, 'store'])
        ->middleware(['user.profile', 'special.password:head_mappings,cash_head_mappings,head_user_mappings'])
        ->name('user-details.store');
    Route::match(['put', 'patch'], 'user-details/{user_detail}', [UserDetailController::class, 'update'])
        ->middleware(['user.profile', 'special.password:head_mappings,cash_head_mappings,head_user_mappings'])
        ->name('user-details.update');
    Route::match(['put', 'patch'], 'user-details/{user_detail}/status', [UserStatusController::class, 'update'])
        ->whereNumber('user_detail')
        ->name('user-details.status');
    Route::delete('user-details/{user_detail}', [UserDetailController::class, 'destroy'])
        ->middleware('special.password')
        ->name('user-details.destroy');

    // Assigning users to heads (stock and cash): always the special password.
    // HeadEmployeeMappingController has no show() or destroy().
    Route::get('head-employee-mappings', [HeadEmployeeMappingController::class, 'index'])
        ->name('head-employee-mappings.index');
    Route::get('cash-head-employee-mappings', [CashHeadEmployeeMappingController::class, 'index'])
        ->name('cash-head-employee-mappings.index');
    Route::get('cash-head-employee-mappings/{cash_head_employee_mapping}', [CashHeadEmployeeMappingController::class, 'show'])
        ->name('cash-head-employee-mappings.show');
    Route::middleware('special.password')->group(function () {
        Route::post('head-employee-mappings', [HeadEmployeeMappingController::class, 'store'])
            ->name('head-employee-mappings.store');
        Route::match(['put', 'patch'], 'head-employee-mappings/{head_employee_mapping}', [HeadEmployeeMappingController::class, 'update'])
            ->name('head-employee-mappings.update');
        Route::post('cash-head-employee-mappings', [CashHeadEmployeeMappingController::class, 'store'])
            ->name('cash-head-employee-mappings.store');
        Route::match(['put', 'patch'], 'cash-head-employee-mappings/{cash_head_employee_mapping}', [CashHeadEmployeeMappingController::class, 'update'])
            ->name('cash-head-employee-mappings.update');
        Route::delete('cash-head-employee-mappings/{cash_head_employee_mapping}', [CashHeadEmployeeMappingController::class, 'destroy'])
            ->name('cash-head-employee-mappings.destroy');
    });

    // Roles (agreed 2026-11-20): admins create and view them — a name and an
    // active status. No edit or delete. The list also feeds the Users form's
    // role picker.
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');

    // Read-only lookups for the Users form's item and customer touch pickers;
    // heads manage both lists in the app.
    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('customer-touch', [CustomerTouchController::class, 'index'])->name('customer-touch.index');
});
