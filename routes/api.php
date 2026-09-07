<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PriceAlertController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/alerts', [PriceAlertController::class, 'store'])
        ->middleware('throttle:60,1');

    Route::delete('/alerts/{alert}', [PriceAlertController::class, 'destroy'])
        ->middleware('throttle:60,1');
});
