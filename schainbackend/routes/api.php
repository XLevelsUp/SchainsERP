<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserDetailController;
use App\Http\Controllers\Api\CustomerTouchController;
use App\Http\Controllers\Api\UsersItemsMappingController;
use App\Http\Controllers\Api\HeadEmployeeMappingController;
use App\Http\Controllers\Api\CashHeadEmployeeMappingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashTxnDetailController;
use App\Http\Controllers\Api\BankDetailController;
use App\Http\Controllers\Api\SaleGoldController;
use App\Http\Controllers\Api\StockDetailsController;

// User Details

/*
|--------------------------------------------------------------------------
| API VERSION 1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
Route::apiResource('user-details', UserDetailController::class);
// Items
Route::apiResource('items', ItemController::class);

// Fitem Boxes
Route::apiResource('fitem-boxes', FitemBoxController::class);

// Users Items Mappings
Route::apiResource(
    'users-items-mappings',
    UsersItemsMappingController::class
);


    /*
    |--------------------------------------------------------------------------
    | Head Employee Mappings
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'head-employee-mappings',
        HeadEmployeeMappingController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Cash Head Employee Mappings
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'cash-head-employee-mappings',
        CashHeadEmployeeMappingController::class
    );


Route::post('/login', [AuthController::class, 'login']);

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );


    /*
    |--------------------------------------------------------------------------
    | Cash Transaction Details
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'cash-txn-details',
        CashTxnDetailController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Cash Transaction Images
    |--------------------------------------------------------------------------
    */

    Route::post(
        'cash-txn-details/{id}/images',
        [CashTxnDetailController::class, 'addImages']
    );


    Route::delete(
        'cash-txn-images/{imageId}',
        [CashTxnDetailController::class, 'deleteImage']
    );

    //BankDetailController
    Route::apiResource(
        'bank-details',
        BankDetailController::class
    );

   //SaleGoldController
    Route::apiResource(
        'sale-gold',
        SaleGoldController::class
    );
});



Route::prefix('v1/stock')->group(function () {
    Route::post('out', [StockDetailsController::class, 'postStockOut']);
    Route::post('in', [StockDetailsController::class, 'postStockIn']);
    Route::post('item-change', [StockDetailsController::class, 'postItemChange']);
    Route::post('item-conversion', [StockDetailsController::class, 'postItemConversion']);
    Route::post('gms', [StockDetailsController::class, 'postGmsOut']);
    Route::post('gms-in', [StockDetailsController::class, 'postGmsIn']);
    Route::post('numeric-waste', [StockDetailsController::class, 'postNumericWaste']);
    Route::post('numeric-waste-in', [StockDetailsController::class, 'postNumericWasteIn']);
    Route::post('hide', [StockDetailsController::class, 'postHide']);
    Route::post('cash', [StockDetailsController::class, 'postCash']);
    Route::post('auto-entry', [StockDetailsController::class, 'postAutoEntry']);
    Route::get('reports/items-obcb', [StockDetailsController::class, 'getHistoryItemsObcb']);
});