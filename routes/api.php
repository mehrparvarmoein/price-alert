<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PriceAlertController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/alerts', [PriceAlertController::class, 'store']);
});

