<?php

use App\Api\Rider\AbsenceController;
use App\Api\Rider\ApplicationController;
use App\Api\Rider\AutoRenewalController;
use App\Api\Rider\DataDeletionController;
use App\Api\Rider\DestinationController;
use App\Api\Rider\GuestController;
use App\Api\Rider\HomeController;
use App\Api\Rider\PlanController;
use App\Api\Rider\PurchaseController;
use App\Api\Rider\SearchController;
use App\Api\Rider\TripController;
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
        Route::get('search', [SearchController::class, 'index'])->middleware('throttle:60,1');
        Route::post('application', [ApplicationController::class, 'store']);
        Route::delete('application', [ApplicationController::class, 'destroy']);
        Route::put('destination', [DestinationController::class, 'update'])->middleware('throttle:30,1');
    });

    Route::middleware('permission:subscriptions.buy,app')->group(function () {
        Route::get('plans', [PlanController::class, 'index']);
        Route::post('purchase', [PurchaseController::class, 'store'])->middleware('throttle:10,1');
        Route::get('purchase', [PurchaseController::class, 'show']);
        Route::get('auto-renewal', [AutoRenewalController::class, 'show']);
        Route::post('auto-renewal', [AutoRenewalController::class, 'store'])->middleware('throttle:10,1');
        Route::post('auto-renewal/code', [AutoRenewalController::class, 'code'])->middleware('throttle:10,1');
        Route::delete('auto-renewal', [AutoRenewalController::class, 'destroy']);
    });

    Route::middleware('permission:trips.follow,app')->group(function () {
        Route::get('trips', [TripController::class, 'index']);
        Route::get('trips/history', [TripController::class, 'history']);
        Route::post('trips/alight', [TripController::class, 'alight'])->middleware('throttle:10,1');
    });

    Route::get('route-map', [TripController::class, 'route'])->middleware('permission:riders.profile.manage,app');

    Route::middleware('permission:subscriptions.absence.give,app')->group(function () {
        Route::get('absences/preview', [AbsenceController::class, 'preview']);
        Route::post('absences', [AbsenceController::class, 'store']);
    });

    Route::put('guests', [GuestController::class, 'update'])->middleware(['permission:subscriptions.guests.reserve,app', 'throttle:30,1']);

    Route::middleware('permission:riders.data.delete,app')->group(function () {
        Route::post('data-deletion', [DataDeletionController::class, 'store']);
        Route::delete('data-deletion', [DataDeletionController::class, 'destroy']);
    });
});
