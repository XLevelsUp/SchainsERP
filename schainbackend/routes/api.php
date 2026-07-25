<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserDetailController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\UsersItemsMappingController;
use App\Http\Controllers\Api\HeadEmployeeMappingController;
use App\Http\Controllers\Api\CashHeadEmployeeMappingController;


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