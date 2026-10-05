<?php

use App\Api\Driver\CrewController;
use App\Api\Driver\EarningsController;
use Illuminate\Support\Facades\Route;

/*
 * The driver and mate side of the mobile app: /api/driver/v1
 *
 * Same app, same login as riders. What an account can reach here depends on its permissions.
 * The mate marks pickups, because the driver is driving. Both phones send the position.
 */
Route::prefix('driver/v1')->middleware('auth:app')->group(function () {
    Route::get('today', [CrewController::class, 'today'])->middleware('permission:trips.riders.view,app');

    Route::middleware('permission:trips.run,app')->group(function () {
        Route::post('trip/start', [CrewController::class, 'start']);
        Route::post('trip/end', [CrewController::class, 'end']);
    });

    Route::post('trip/pickups', [CrewController::class, 'pickup'])->middleware('permission:trips.pickups.mark,app');
    Route::get('earnings', [EarningsController::class, 'show'])->middleware('permission:drivers.commission.view,app');
    Route::post('trip/location', [CrewController::class, 'location'])->middleware('permission:trips.location.send,app');
});
