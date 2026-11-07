<?php

namespace App\Api\Rider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Queries\FindRuns;

class SearchController
{
    /**
     * Where to? Vans that go to a place.
     *
     * Matches stop names, landmarks and route destinations. Send her position to have the stop nearest to her
     * picked for getting on, with how far it is. Nearest first. `apply_stop_id` and `departs_at` fill in an application.
     */
    public function index(Request $request, FindRuns $find): JsonResponse
    {
        $data = $request->validate([
            /** What she typed, such as "Circle" or "Makola" */
            'to' => ['required', 'string', 'min:2', 'max:60'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);

        $latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;

        return response()->json([
            /** @var list<array{run_id: int, route_id: int, route: string, direction: 'outbound'|'return', departs_at: string, days: list<int>, seats_free: int, apply_stop_id: int, board: array{stop_id: int, name: string, landmark: string|null, time: string|null, metres_away: int|null}, alight: array{stop_id: int, name: string, landmark: string|null, time: string|null}}> */
            'runs' => $find->handle($data['to'], $latitude, $longitude)->all(),
        ]);
    }
}
