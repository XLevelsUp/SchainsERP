<?php

use Illuminate\Support\Facades\Route;


use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserDetailController;
use App\Http\Controllers\Api\CustomerTouchController;
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


Route::prefix('v1')->group(function () {
    Route::apiResource('user-details', UserDetailController::class);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('fitem-boxes', FitemBoxController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('customer-touch', CustomerTouchController::class);
    Route::apiResource('users-items-mappings', UsersItemsMappingController::class);
    Route::apiResource('head-employee-mappings', HeadEmployeeMappingController::class);
    Route::apiResource('cash-head-employee-mappings', CashHeadEmployeeMappingController::class);
    Route::post('/login', [AuthController::class, 'login']);

    // Cash Dashboard APIs (Must be above apiResource to prevent {id} interception)
    Route::get('report/cash-transactions-obcb', [ReportController::class, 'getCashTransactionsObcb']);
    Route::get('cash-txn-details/out-history', [CashTxnDetailController::class, 'getOutHistory']);
    Route::get('cash-txn-details/in-history', [CashTxnDetailController::class, 'getInHistory']);
    Route::get('cash-txn-details/print-report', [CashTxnDetailController::class, 'getPrintReport']);
    // Route::get('stock-details/history',        [StockDetailsController::class, 'getHistory']);
    Route::get('stock-details/cash-transaction-history', [StockDetailsController::class, 'getCashTransactionHistory']);
    Route::get('stock-details/available-metals', [StockDetailsController::class, 'getAvailableMetals']);

    Route::apiResource('cash-txn-details', CashTxnDetailController::class);
    Route::apiResource('cash-categories', CashCategoryController::class);
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
});




Route::prefix('v1/stock')->group(function () {
    Route::post('out', [StockDetailsController::class, 'postStockOut']);
    Route::post('in', [StockDetailsController::class, 'postStockIn']);
    Route::post('item-change', [StockDetailsController::class, 'postItemChange']);
    Route::post('item-conversion', [StockDetailsController::class, 'postItemConversion']);
    // Route::post('gms-out', [StockDetailsController::class, 'postGmsOut']);
    // Route::post('gms-in', [StockDetailsController::class, 'postGmsIn']);
    // Route::post('numeric-waste', [StockDetailsController::class, 'postNumericWaste']);
    // Route::post('numeric-waste-in', [StockDetailsController::class, 'postNumericWasteIn']);
    // Route::post('hide', [StockDetailsController::class, 'postHide']);
    // Route::post('cash', [StockDetailsController::class, 'postCash']);
    // Route::post('auto-entry', [StockDetailsController::class, 'postAutoEntry']);
    // Route::get('reports/items-obcb', [StockDetailsController::class, 'getHistoryItemsObcb']);
});