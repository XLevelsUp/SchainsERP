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
use App\Http\Controllers\Api\StockDetailsController;

// User Details
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

// Stock Module Transactions
Route::prefix('v1/stock')->group(function () {
    Route::post('out', [StockDetailsController::class, 'postStockOut']);
    Route::post('item-change', [StockDetailsController::class, 'postItemChange']);
    Route::post('item-conversion', [StockDetailsController::class, 'postItemConversion']);
    Route::post('gms', [StockDetailsController::class, 'postGmsOut']);
    Route::post('numeric-waste', [StockDetailsController::class, 'postNumericWaste']);
    Route::post('hide', [StockDetailsController::class, 'postHide']);
    Route::post('cash', [StockDetailsController::class, 'postCash']);
});