<?php

use Illuminate\Support\Facades\Route;

/*
 * The driver and mate side of the mobile app: /api/driver/v1
 *
 * Same app, same login as riders. What an account can reach here depends on its permissions.
 */
Route::prefix('driver/v1')->middleware('auth:app')->group(function () {
    // Placeholder until the driver and mate screens are built (tasks D2 to D8).
    Route::get('home', fn () => ['screens' => 'driver'])->middleware('permission:trips.riders.view,app');
});
