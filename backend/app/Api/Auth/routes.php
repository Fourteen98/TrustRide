<?php

use App\Api\Auth\AuthController;
use App\Api\Auth\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('code', [AuthController::class, 'requestCode'])->middleware('throttle:5,1');
    Route::post('verify', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');

    Route::middleware('auth:app')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('push-device', [NotificationController::class, 'register']);
        Route::put('preferred-channel', [NotificationController::class, 'prefer']);
    });
});
