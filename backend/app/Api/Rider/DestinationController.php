<?php

namespace App\Api\Rider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Actions\SetDestination;
use Modules\Subscriptions\Models\Seat;

class DestinationController
{
    use ResolvesRider;

    /**
     * Say where she goes on her route.
     *
     * The stop she gets off at going out, and gets on at coming back. It is set on all her seats on that route.
     * The stop must come after hers. The end of the route is where everyone goes unless they say otherwise.
     */
    public function update(Request $request, SetDestination $set): JsonResponse
    {
        $data = $request->validate([
            /** One of her seats on the route, from `GET home` */
            'seat_id' => ['required', 'integer'],
            /** A stop on the route, after hers */
            'stop_id' => ['required', 'integer'],
        ]);

        $seat = Seat::query()->where('rider_id', $this->riderOrFail($request)->id)->findOrFail($data['seat_id']);
        $set->handle($seat, $data['stop_id']);
        $seat = $seat->fresh(['run.route.stops']);

        return response()->json([
            /** Where she goes now, "Kaneshie" */
            'destination' => $seat->run->route->stops->firstWhere('id', $seat->destinationStopId())->name,
        ]);
    }
}
