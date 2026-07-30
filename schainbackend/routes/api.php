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


/*
|--------------------------------------------------------------------------
| API VERSION 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Items
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'items',
        ItemController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Fitem Boxes
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'fitem-boxes',
        FitemBoxController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'roles',
        RoleController::class
    );


    /*
    |--------------------------------------------------------------------------
    | User Details
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'user-details',
        UserDetailController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Touch
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'customer-touch',
        CustomerTouchController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Users Items Mappings
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

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
});