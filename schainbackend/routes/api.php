<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserDetailController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\UsersItemsMappingController;
use App\Http\Controllers\Api\HeadEmployeeMappingController;
use App\Http\Controllers\Api\CashHeadEmployeeMappingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashTxnDetailController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\CustomerTouchController;





// Items
Route::apiResource('items', ItemController::class);

// Fitem Boxes
Route::apiResource('fitem-boxes', FitemBoxController::class);

//roles
Route::apiResource('roles', RoleController::class);
// User Details
Route::apiResource(
    'user-details',
    UserDetailController::class
);

//customer_touch
Route::apiResource(
    'customer-touch',
    CustomerTouchController::class
);

// Users Items Mappings
Route::apiResource(
    'users-items-mappings',
    UsersItemsMappingController::class
);

// Head Employee Mappings
Route::apiResource(
    'head-employee-mappings',
    HeadEmployeeMappingController::class
);

// Cash Head Employee Mappings
Route::apiResource(
    'cash-head-employee-mappings',
    CashHeadEmployeeMappingController::class
);

//Auth_login
Route::post('/login', [AuthController::class, 'login']);

//cashtxndetailcontroller
Route::apiResource(
    'cash-txn-details',
    CashTxnDetailController::class
);

Route::post(
    'cash-txn-details/{id}/images',
    [CashTxnDetailController::class, 'addImages']
);

Route::delete(
    'cash-txn-images/{imageId}',
    [CashTxnDetailController::class, 'deleteImage']
);