<?php

use App\Web\ParcelPageController;
use App\Web\TripPageController;
use Illuminate\Support\Facades\Route;

// The admin dashboard lives at /admin (see app/Admin/AdminPanelProvider.php).
Route::redirect('/', '/admin');

// The live map behind a trip share link. No login. See App\Web\TripPageController.
Route::get('t/{publicId}', [TripPageController::class, 'show'])->middleware('throttle:120,1');
Route::get('t/{publicId}/data', [TripPageController::class, 'data'])->middleware('throttle:120,1');

// An Ox customer's parcel page, the link in her SMS. No login. See App\Web\ParcelPageController.
Route::get('o/{publicId}', [ParcelPageController::class, 'show'])->middleware('throttle:120,1');
