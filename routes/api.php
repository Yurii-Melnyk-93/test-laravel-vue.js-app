<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PromoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Claiming moves money, so it is rate limited more tightly than reads.
    Route::post('/promo/claim', [PromoController::class, 'claim'])
        ->middleware('throttle:20,1');
});
