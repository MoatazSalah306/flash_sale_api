<?php

use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

Route::middleware(['throttle:5,1','apply_timezone'])->group(function () { 
    Route::post('/holds', [HoldController::class, 'create']);
    Route::post('/orders', [OrderController::class, 'create']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
