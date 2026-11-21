<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserDetailController;
use App\Http\Controllers\Api\CustomerTouchController;
use App\Http\Controllers\Api\CustomerTouchUserMappingController;
use App\Http\Controllers\Api\UsersItemsMappingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\HeadEmployeeMappingController;
use App\Http\Controllers\Api\CashHeadEmployeeMappingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashTxnDetailController;
use App\Http\Controllers\Api\BankDetailController;
use App\Http\Controllers\Api\SaleGoldController;
use App\Http\Controllers\Api\PurchaseGoldController;
use App\Http\Controllers\Api\StockDetailsController;
use App\Http\Controllers\Api\CashToGoldController;
use App\Http\Controllers\Api\GoldToCashController;
use App\Http\Controllers\Api\CashCategoryController;
use App\Http\Controllers\Api\PhoneBookController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SystemLogController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes. App tokens only: admin-console tokens belong to the
    // `admins` provider and Passport refuses them on this guard
    // (App\Support\AdminAccess).
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Administration (BACKEND_TODO §26, agreed 2026-11-12): anyone signed
        // in may read these (the screens' pickers need them); only a HEAD may
        // change them, below.
        //
        // Users, their heads and roles are the exception (agreed 2026-11-20):
        // the admin console creates, edits and deletes users, assigns heads
        // and creates roles (routes/admin.php). The app keeps one creation,
        // Add Retailer on the Stock screen, limited to that customer by
        // EnsureQuickAddCustomer.
        $adminResources = [
            'user-details' => UserDetailController::class,
            'items' => ItemController::class,
            'fitem-boxes' => FitemBoxController::class,
            'roles' => RoleController::class,
            'customer-touch' => CustomerTouchController::class,
            'users-items-mappings' => UsersItemsMappingController::class,
            'head-employee-mappings' => HeadEmployeeMappingController::class,
            'cash-head-employee-mappings' => CashHeadEmployeeMappingController::class,
        ];
        foreach ($adminResources as $name => $controller) {
            // HeadEmployeeMappingController has no show().
            $only = $name === 'head-employee-mappings' ? ['index'] : ['index', 'show'];
            Route::apiResource($name, $controller)->only($only);
        }
        Route::post('user-details', [UserDetailController::class, 'store'])
            ->middleware(['act.as.head', 'quick.add.customer'])
            ->name('user-details.store');
        Route::middleware('head.only')->group(function () use ($adminResources) {
            // Changed only in the admin console (routes/admin.php).
            $adminOwned = ['user-details', 'roles', 'head-employee-mappings', 'cash-head-employee-mappings'];
            foreach ($adminResources as $name => $controller) {
                if (!in_array($name, $adminOwned, true)) {
                    Route::apiResource($name, $controller)->except(['index', 'show']);
                }
            }

            Route::post('customer-touch-user-mappings', [CustomerTouchUserMappingController::class, 'store']);
            Route::put('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'update']);
            Route::patch('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'update']);
            Route::delete('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'destroy']);
        });

        Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class);
        Route::post('fitems', [\App\Http\Controllers\Api\FitemController::class, 'store'])->middleware('act.as.head');

        Route::get('customer-touch-user-mappings', [CustomerTouchUserMappingController::class, 'index']);

        // Customer comments are saved from the Stock screen while entering
        // values, so operators with "Can act as head" may save them too.
        Route::put('user-details/{id}/update-cc', [UserDetailController::class, 'updateCc'])->middleware('act.as.head');
        Route::patch('user-details/{id}/update-cc', [UserDetailController::class, 'updateCc'])->middleware('act.as.head');

        // Cash Dashboard APIs (Must be above apiResource to prevent {id} interception)
        Route::get('report/cash-transactions-obcb', [ReportController::class, 'getCashTransactionsObcb']);
        Route::get('report/live-metal-balance', [ReportController::class, 'getLiveMetalBalance']);
        Route::get('report/one-day-action', [ReportController::class, 'getOneDayActionReport']);
        Route::get('report/daywise-grand', [ReportController::class, 'getDaywiseGrandReport']);
        Route::get('cash-txn-details/out-history', [CashTxnDetailController::class, 'getOutHistory']);
        Route::get('cash-txn-details/in-history', [CashTxnDetailController::class, 'getInHistory']);
        Route::get('cash-txn-details/print-report', [CashTxnDetailController::class, 'getPrintReport']);
        Route::get('stock-details/history',        [StockDetailsController::class, 'getHistory']);
        Route::get('stock-details/cash-transaction-history', [StockDetailsController::class, 'getCashTransactionHistory']);
        Route::get('stock-details/available-metals', [StockDetailsController::class, 'getAvailableMetals']);
        Route::get('stock-details/head-stocks', [StockDetailsController::class, 'getHeadStocks']);
        Route::get('stock-details/available-lots', [StockDetailsController::class, 'getAvailableStockLots']);

        // The old store/update/destroy used column names the table no longer
        // has (DELETE removed an entry without restoring either balance), so
        // they stay unregistered. Deleting isn't allowed (agreed 2026-11-12).
        // New entries go through cash-txn-details/in, /out and /auto-entry;
        // edits through editEntry, heads only (BACKEND_TODO §17).
        Route::apiResource('cash-txn-details', CashTxnDetailController::class)->only(['index', 'show']);
        Route::put('cash-txn-details/{id}', [CashTxnDetailController::class, 'editEntry'])->middleware('head.only');
        Route::patch('cash-txn-details/{id}', [CashTxnDetailController::class, 'editEntry'])->middleware('head.only');

        // Gold entries are corrected, not edited in place: a reversal plus the
        // corrected entry (BACKEND_TODO §17 phase 3). Heads only.
        Route::middleware('head.only')->group(function () {
            Route::get('gold-entries/{id}', [\App\Http\Controllers\Api\GoldEntryController::class, 'show'])->whereNumber('id');
            Route::post('gold-entries/{id}/correct', [\App\Http\Controllers\Api\GoldEntryController::class, 'correct'])->whereNumber('id');
            // Numeric wastage lines the same way; {id} is the stock row id (§17).
            Route::get('numeric-wastage-entries/{direction}/{id}', [\App\Http\Controllers\Api\NumericWastageEntryController::class, 'show'])
                ->whereIn('direction', ['in', 'out'])->whereNumber('id');
            Route::post('numeric-wastage-entries/{direction}/{id}/correct', [\App\Http\Controllers\Api\NumericWastageEntryController::class, 'correct'])
                ->whereIn('direction', ['in', 'out'])->whereNumber('id');
        });
        // Cash categories and banks: read by everyone, changed by heads only (§26).
        Route::apiResource('cash-categories', CashCategoryController::class)->only(['index', 'show']);
        Route::apiResource('bank-details', BankDetailController::class)->only(['index', 'show']);
        Route::middleware('head.only')->group(function () {
            Route::apiResource('cash-categories', CashCategoryController::class)->except(['index', 'show']);
            Route::apiResource('bank-details', BankDetailController::class)->except(['index', 'show']);
        });
        Route::apiResource('phone-book', PhoneBookController::class)->only(['index', 'show']);
        Route::post('cash-txn-details/{id}/images', [CashTxnDetailController::class, 'addImages'])->middleware('act.as.head');
        Route::delete('cash-txn-images/{imageId}', [CashTxnDetailController::class, 'deleteImage'])->middleware('act.as.head');
        Route::apiResource('reminders', ReminderController::class);

        // Entering cash and gold values: heads, or users with the "can act as
        // head" permission (EnsureCanActAsHead).
        Route::middleware('act.as.head')->group(function () {
            Route::post('purchase-gold', [PurchaseGoldController::class, 'store']);
            Route::post('sale-gold', [SaleGoldController::class, 'store']);
            Route::post('cash-txn-details/in', [CashTxnDetailController::class, 'postIncome']);
            Route::post('cash-txn-details/out', [CashTxnDetailController::class, 'postExpense']);
            Route::post('cash-txn-details/auto-entry', [CashTxnDetailController::class, 'autoEntry']);
            Route::post('cash-to-gold', [CashToGoldController::class, 'store']);
            Route::post('gold-to-cash', [GoldToCashController::class, 'store']);
        });
        
        // System Settings: read by everyone (the stock screens read
        // metal_popup_items), changed by heads only (§26).
        Route::apiResource('settings', \App\Http\Controllers\Api\SystemSettingController::class)
            ->only(['index', 'show'])
            ->parameters(['settings' => 'key']);
        Route::apiResource('settings', \App\Http\Controllers\Api\SystemSettingController::class)
            ->except(['index', 'show'])
            ->parameters(['settings' => 'key'])
            ->middleware('head.only');

        // System Audit Logs: heads only (checked in the controller). Admin
        // console actions appear here too (module ADMIN).
        Route::get('system-logs/filters', [SystemLogController::class, 'filters']);
        Route::get('system-logs', [SystemLogController::class, 'index']);
        Route::get('system-logs/{id}', [SystemLogController::class, 'show']);
    });
});

Route::prefix('v1/stock')->middleware('auth:api')->group(function () {
    // Entering stock values: heads, or users with the "can act as head"
    // permission (EnsureCanActAsHead).
    Route::middleware('act.as.head')->group(function () {
        Route::post('out', [StockDetailsController::class, 'postStockOut']);
        Route::post('in', [StockDetailsController::class, 'postStockIn']);
        Route::post('item-change', [StockDetailsController::class, 'postItemChange']);
        Route::post('item-conversion', [StockDetailsController::class, 'postItemConversion']);
        Route::post('gms-out', [StockDetailsController::class, 'postGmsOut']);
        Route::post('gms-in', [StockDetailsController::class, 'postGmsIn']);
        Route::post('numeric-waste', [StockDetailsController::class, 'postNumericWaste']);
        Route::post('numeric-waste-in', [StockDetailsController::class, 'postNumericWasteIn']);
        Route::post('auto-entry', [StockDetailsController::class, 'postAutoEntry']);
        Route::post('hide', [StockDetailsController::class, 'postHide']);
        Route::post('unhide', [StockDetailsController::class, 'postUnhide']);
        Route::post('cash-out', [StockDetailsController::class, 'postCash']);
    });

    Route::get('reports/items-obcb/export', [StockDetailsController::class, 'exportHistoryItemsObcb']);
    Route::get('reports/items-obcb', [StockDetailsController::class, 'getHistoryItemsObcb']);
    Route::get('reports/consolidated/export', [StockDetailsController::class, 'exportConsolidatedReport']);
    Route::get('reports/consolidated', [StockDetailsController::class, 'getConsolidatedReport']);
    Route::get('reports/id-wise', [StockDetailsController::class, 'getIdWiseReport']);
});