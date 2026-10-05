<?php

use App\Api\Webhooks\HubtelController;
use Illuminate\Support\Facades\Route;

/*
 * Calls from other companies' servers. No login, because they can't have one.
 * What makes each safe is written on its controller.
 */
Route::prefix('webhooks')->middleware('throttle:120,1')->group(function () {
    Route::post('hubtel/payment', [HubtelController::class, 'payment']);
    Route::post('hubtel/preapproval', [HubtelController::class, 'preapproval']);
});
