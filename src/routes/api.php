<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaxProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'Ok']);
});

Route::prefix('auth')->group(function () {
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:api')->get('me', [AuthController::class, 'me']);
});

Route::apiResource('user', UserController::class);
Route::post('user/list', [UserController::class, 'list']);
Route::apiResource('tax-profile', TaxProfileController::class);
Route::post('tax-profile/list', [TaxProfileController::class, 'list']);
Route::apiResource('invoice', InvoiceController::class);
Route::post('invoice/list', [InvoiceController::class, 'list']);
