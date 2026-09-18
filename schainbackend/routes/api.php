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

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::apiResource('user-details', UserDetailController::class);
        Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class);
        Route::apiResource('items', ItemController::class);
        Route::apiResource('fitem-boxes', FitemBoxController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('customer-touch', CustomerTouchController::class);
        Route::post('fitems', [\App\Http\Controllers\Api\FitemController::class, 'store']);
        
        // Customer Touch User Mappings
        Route::get('customer-touch-user-mappings', [CustomerTouchUserMappingController::class, 'index']);
        Route::post('customer-touch-user-mappings', [CustomerTouchUserMappingController::class, 'store']);
        Route::put('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'update']);
        Route::patch('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'update']);
        Route::delete('customer-touch-user-mappings/{id}', [CustomerTouchUserMappingController::class, 'destroy']);
        
        // Update CC
        Route::put('user-details/{id}/update-cc', [UserDetailController::class, 'updateCc']);
        Route::patch('user-details/{id}/update-cc', [UserDetailController::class, 'updateCc']);
        Route::apiResource('users-items-mappings', UsersItemsMappingController::class);
        Route::apiResource('head-employee-mappings', HeadEmployeeMappingController::class);
        Route::apiResource('cash-head-employee-mappings', CashHeadEmployeeMappingController::class);

        // Cash Dashboard APIs (Must be above apiResource to prevent {id} interception)
        Route::get('report/cash-transactions-obcb', [ReportController::class, 'getCashTransactionsObcb']);
        Route::get('report/live-metal-balance', [ReportController::class, 'getLiveMetalBalance']);
        Route::get('report/one-day-action', [ReportController::class, 'getOneDayActionReport']);
        Route::get('cash-txn-details/out-history', [CashTxnDetailController::class, 'getOutHistory']);
        Route::get('cash-txn-details/in-history', [CashTxnDetailController::class, 'getInHistory']);
        Route::get('cash-txn-details/print-report', [CashTxnDetailController::class, 'getPrintReport']);
        Route::get('stock-details/history',        [StockDetailsController::class, 'getHistory']);
        Route::get('stock-details/cash-transaction-history', [StockDetailsController::class, 'getCashTransactionHistory']);
        Route::get('stock-details/available-metals', [StockDetailsController::class, 'getAvailableMetals']);
        Route::get('stock-details/head-stocks', [StockDetailsController::class, 'getHeadStocks']);
        Route::get('stock-details/available-lots', [StockDetailsController::class, 'getAvailableStockLots']);

        Route::apiResource('cash-txn-details', CashTxnDetailController::class);
        Route::apiResource('cash-categories', CashCategoryController::class);
        Route::apiResource('phone-book', PhoneBookController::class);
        Route::post('cash-txn-details/{id}/images', [CashTxnDetailController::class, 'addImages']);
        Route::delete('cash-txn-images/{imageId}', [CashTxnDetailController::class, 'deleteImage']);
        Route::apiResource('bank-details', BankDetailController::class);

        Route::post('purchase-gold', [PurchaseGoldController::class, 'store']);
        Route::post('sale-gold', [SaleGoldController::class, 'store']);
        Route::post('cash-txn-details/in', [CashTxnDetailController::class, 'postIncome']);
        Route::post('cash-txn-details/out', [CashTxnDetailController::class, 'postExpense']);
        Route::post('cash-txn-details/auto-entry', [CashTxnDetailController::class, 'autoEntry']);
        Route::post('cash-to-gold', [CashToGoldController::class, 'store']);
        Route::post('gold-to-cash', [GoldToCashController::class, 'store']);
        
        // System Settings
        Route::apiResource('settings', \App\Http\Controllers\Api\SystemSettingController::class)->parameters([
            'settings' => 'key'
        ]);
    });
});

Route::prefix('v1/stock')->middleware('auth:api')->group(function () {
    Route::post('out', [StockDetailsController::class, 'postStockOut']);
    Route::post('in', [StockDetailsController::class, 'postStockIn']);
    Route::post('item-change', [StockDetailsController::class, 'postItemChange']);
    Route::post('item-conversion', [StockDetailsController::class, 'postItemConversion']);
    Route::post('gms-out', [StockDetailsController::class, 'postGmsOut']);
    Route::post('gms-in', [StockDetailsController::class, 'postGmsIn']);
    Route::post('numeric-waste', [StockDetailsController::class, 'postNumericWaste']);
    Route::post('numeric-waste-in', [StockDetailsController::class, 'postNumericWasteIn']);
    Route::post('auto-entry', [StockDetailsController::class, 'postAutoEntry']);
    Route::get('reports/items-obcb/export', [StockDetailsController::class, 'exportHistoryItemsObcb']);
    Route::get('reports/items-obcb', [StockDetailsController::class, 'getHistoryItemsObcb']);
    Route::get('reports/consolidated/export', [StockDetailsController::class, 'exportConsolidatedReport']);
    Route::get('reports/consolidated', [StockDetailsController::class, 'getConsolidatedReport']);
    Route::get('reports/id-wise', [StockDetailsController::class, 'getIdWiseReport']);
    Route::post('hide', [StockDetailsController::class, 'postHide']);
    Route::post('cash-out', [StockDetailsController::class, 'postCash']);
});