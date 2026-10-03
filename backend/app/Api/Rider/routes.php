<?php

use App\Api\Rider\AbsenceController;
use App\Api\Rider\ApplicationController;
use App\Api\Rider\DataDeletionController;
use App\Api\Rider\HomeController;
use App\Api\Rider\PlanController;
use Illuminate\Support\Facades\Route;

/*
 * The rider side of the mobile app: /api/rider/v1
 *
 * Controllers here stay thin. They check the permission, call a module Action or Query,
 * and shape the reply. No business rules.
 *
 * There is no rider id in any URL. Every endpoint works on the logged-in account's own record.
 */
Route::prefix('rider/v1')->middleware('auth:app')->group(function () {
    Route::middleware('permission:riders.profile.manage,app')->group(function () {
        Route::get('home', [HomeController::class, 'show']);
        Route::get('routes', [ApplicationController::class, 'routes']);
        Route::post('application', [ApplicationController::class, 'store']);
        Route::delete('application', [ApplicationController::class, 'destroy']);
    });

    Route::get('plans', [PlanController::class, 'index'])->middleware('permission:subscriptions.buy,app');

    Route::middleware('permission:subscriptions.absence.give,app')->group(function () {
        Route::get('absences/preview', [AbsenceController::class, 'preview']);
        Route::post('absences', [AbsenceController::class, 'store']);
    });

    Route::middleware('permission:riders.data.delete,app')->group(function () {
        Route::post('data-deletion', [DataDeletionController::class, 'store']);
        Route::delete('data-deletion', [DataDeletionController::class, 'destroy']);
    });
});
