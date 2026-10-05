<?php

namespace App\Api\Rider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Models\Trip;
use Modules\Trips\Queries\TripProgress;

class TripController
{
    use ResolvesRider;

    /**
     * Where her van is today.
     *
     * One entry for each of her seats that runs today. Ask again every 10 to 15 seconds while a trip is running.
     * `stops_away` comes from the route's stop order, so it needs no map and no paid routing service.
     */
    public function index(Request $request, TripProgress $progress): JsonResponse
    {
        $rider = $this->rider($request);
        $today = today();

        $seats = $rider === null ? collect() : Seat::query()
            ->where('rider_id', $rider->id)
            ->where('status', SeatStatus::Active)
            ->whereIn('rider_id', Subscription::query()->covering($today)->select('rider_id'))
            ->with(['run.route.stops', 'run.vehicle', 'run.driver', 'stop'])
            ->get()
            ->filter(fn (Seat $seat) => $seat->run->is_active && $seat->run->runsOn($today))
            ->sortBy(fn (Seat $seat) => $seat->run->departureTime())
            ->values();

        return response()->json([
            'trips' => $seats->map(function (Seat $seat) use ($progress, $today) {
                $trip = Trip::query()->where('run_id', $seat->run_id)->where('date', $today->toDateString())->with('pickups')->first();
                $state = $progress->handle($seat->run, $trip, $seat->stop_id);

                return [
                    'route' => $seat->run->route->name,
                    'car_number' => $seat->run->vehicle->registration_number,
                    'my_stop' => $seat->stop->name,
                    /** @var string|null When the van is due at her stop, "06:38" */
                    'my_time' => $seat->pickupTime(),
                    /** @var 'not_started'|'running'|'ended' */
                    'status' => $state['status'],
                    /** @var int|null Stops between the van and her. 0 means it is at her stop or has passed it. Null unless running. */
                    'stops_away' => $state['stops_away'],
                    /** @var 'picked_up'|'missed'|null What the mate marked for her today */
                    'pickup' => $trip?->pickups->firstWhere('rider_id', $seat->rider_id)?->status->value,
                    /** @var array{latitude: float, longitude: float, at: string, live: bool}|null Only while the trip is running */
                    'position' => $state['position'],
                    /** @var list<array{id: int, name: string, landmark: string|null, time: string|null, latitude: float|null, longitude: float|null, reached: bool}> */
                    'stops' => $state['stops'],
                    /** A web page with the live map. She can open it or send it to someone. It stops showing the van when the trip ends. */
                    'map_url' => $trip ? url("/t/{$trip->public_id}").'?stop='.$seat->stop_id : null,
                ];
            })->values()->all(),
        ]);
    }
}
