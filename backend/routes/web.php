<?php

use App\Web\TripPageController;
use Illuminate\Support\Facades\Route;

// The admin dashboard lives at /admin (see app/Admin/AdminPanelProvider.php).
Route::redirect('/', '/admin');

// The live map behind a trip share link. No login. See App\Web\TripPageController.
Route::get('t/{publicId}', [TripPageController::class, 'show'])->middleware('throttle:120,1');
Route::get('t/{publicId}/data', [TripPageController::class, 'data'])->middleware('throttle:120,1');
