<?php

use App\Api\Support\SupportController;
use Illuminate\Support\Facades\Route;

/*
 * Asking TrustRide for help: /api/support/v1
 *
 * Shared by every role in the app. A rider, a driver and a mate all write in the same way.
 * There is no case id in any URL. A person has one conversation at a time, and it is her own.
 */
Route::prefix('support/v1')->middleware(['auth:app', 'permission:support.alert.raise,app'])->group(function () {
    Route::get('case', [SupportController::class, 'show']);
    Route::post('messages', [SupportController::class, 'store'])->middleware('throttle:20,1');
});
