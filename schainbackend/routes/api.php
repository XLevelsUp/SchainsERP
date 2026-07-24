<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\FitemBoxController;
use App\Http\Controllers\Api\UserDetailController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('items', ItemController::class);
Route::apiResource('roles', RoleController::class);
Route::apiResource('fitem-boxes', FitemBoxController::class);
Route::apiResource('user-details', UserDetailController::class);

