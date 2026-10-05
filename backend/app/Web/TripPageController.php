<?php

namespace App\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Trips\Models\Trip;
use Modules\Trips\Queries\TripProgress;

/**
 * The live map page behind a share link: /t/{public id}
 *
 * No login. Anyone with the link sees the route, the car and where the van is, and nothing about
 * who is riding. The link can't be guessed, and the position is only given out while the trip runs.
 * The app shows this same page inside itself, and the console links to it.
 */
class TripPageController
{
    public function show(string $publicId): View
    {
        $trip = $this->trip($publicId);

        return view('trip', [
            'trip' => $trip,
            'dataUrl' => url("/t/{$trip->public_id}/data"),
        ]);
    }

    public function data(Request $request, string $publicId, TripProgress $progress): JsonResponse
    {
        $trip = $this->trip($publicId);

        return response()->json([
            'route' => $trip->run->route->name,
            'car_number' => $trip->run->vehicle->registration_number,
            'car_colour' => $trip->run->vehicle->colour,
            ...$progress->handle($trip->run, $trip, $request->integer('stop') ?: null),
        ]);
    }

    private function trip(string $publicId): Trip
    {
        return Trip::query()->where('public_id', $publicId)->with(['run.route.stops', 'run.vehicle'])->firstOrFail();
    }
}
