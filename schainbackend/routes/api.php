<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RoleController;

use App\Http\Controllers\Api\UserDetailController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('items', ItemController::class);
Route::apiResource('roles', RoleController::class);

Route::apiResource('user-details', UserDetailController::class);

Route::get('/user-details', [UserDetailController::class, 'index']);
Route::post('/user-details', [UserDetailController::class, 'store']);
Route::get('/user-details/{id}', [UserDetailController::class, 'show']);
Route::post('/user-details/{id}', [UserDetailController::class, 'update']);
Route::delete('/user-details/{id}', [UserDetailController::class, 'destroy']);