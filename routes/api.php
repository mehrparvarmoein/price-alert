<?php

use App\Http\Controllers\Api\PriceAlertController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/alerts', [PriceAlertController::class, 'store']);
});

