<?php

namespace App\Api\Rider;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Actions\RecordAlighting;
use Modules\Trips\Models\Trip;
use Modules\Trips\Queries\RiderTripHistory;
use Modules\Trips\Queries\TripProgress;
use Modules\Trips\Queries\TripTrail;

class TripController
{
    use ResolvesRider;

    /**
     * Where her van is today.
     *
     * One entry for each of her seats that runs today. Ask again every 10 to 15 seconds while a trip is running.
     * `stops_away` comes from the route's stop order, so it needs no map and no paid routing service.
     */
    public function index(Request $request, TripProgress $progress, TripTrail $trail): JsonResponse
    {
        $rider = $this->rider($request);
        $today = today();

        $seats = $rider === null ? collect() : Seat::query()
            ->where('rider_id', $rider->id)
            ->where('status', SeatStatus::Active)
            ->whereIn('rider_id', Subscription::query()->covering($today)->select('rider_id'))
            ->with(['run.route.stops', 'run.vehicle', 'run.driver'])
            ->get()
            ->filter(fn (Seat $seat) => $seat->run->is_active && $seat->run->runsOn($today))
            ->sortBy(fn (Seat $seat) => $seat->run->departureTime())
            ->values();

        return response()->json([
            'trips' => $seats->map(function (Seat $seat) use ($progress, $trail, $today) {
                $trip = Trip::query()->where('run_id', $seat->run_id)->where('date', $today->toDateString())->with('pickups')->first();
                $state = $progress->handle($seat->run, $trip, $seat->boardsAt());
                $mine = $trip?->pickups->firstWhere('rider_id', $seat->rider_id);
                $stops = $seat->run->route->stops->keyBy('id');
                $reached = $trip?->isRunning() ? $trip->orderOf($trip->reached_stop_id) ?? -1 : null;
                $offIndex = $trip?->orderOf($seat->getsOffAt());

                return [
                    /** For saying she got down. */
                    'run_id' => $seat->run_id,
                    'route' => $seat->run->route->name,
                    'car_number' => $seat->run->vehicle->registration_number,
                    /** Where she gets on */
                    'my_stop' => $stops[$seat->boardsAt()]->name,
                    'my_stop_id' => $seat->boardsAt(),
                    /** @var string|null When the van is due at her stop, "06:38" */
                    'my_time' => $seat->pickupTime(),
                    /** Where she gets off */
                    'off_stop' => $stops[$seat->getsOffAt()]->name,
                    'off_stop_id' => $seat->getsOffAt(),
                    /** @var string|null When the van is due there, by the timetable */
                    'off_time' => $seat->dropTime(),
                    /** @var int|null While she is on board: stops until hers. 1 means hers is next, 0 that the van is there. Null otherwise. */
                    'stops_to_off' => $reached !== null && $offIndex !== null && $mine?->status->value === 'picked_up' && $mine->alighted_at === null
                        ? max(0, $offIndex - $reached) : null,
                    /** @var 'not_started'|'running'|'ended' */
                    'status' => $state['status'],
                    /** @var int|null Stops between the van and her. 0 means it is at her stop or has passed it. Null unless running. */
                    'stops_away' => $state['stops_away'],
                    /** @var 'picked_up'|'missed'|null What the mate marked for her today */
                    'pickup' => $mine?->status->value,
                    /** When she said she got down. Null until she does. */
                    'alighted_at' => $mine?->alighted_at?->toIso8601String(),
                    /** @var array{latitude: float, longitude: float, at: string, live: bool, heading: float|null}|null Only while the trip is running */
                    'position' => $state['position'],
                    /** Minutes until the van reaches her stop in today's traffic. Null when it can't be worked out: then show stops_away. */
                    'minutes_away' => $state['minutes_away'],
                    /** @var list<array{0: float, 1: float}>|null The road the run follows, as [latitude, longitude]. Null until traced: then join the stops. */
                    'path' => $state['path'],
                    /** The road the van has driven so far today. Empty unless running. */
                    'trail' => $trail->handle($trip),
                    /** @var list<array{id: int, name: string, landmark: string|null, time: string|null, latitude: float|null, longitude: float|null, reached: bool}> */
                    'stops' => $state['stops'],
                    /** A web page with the live map. She can open it or send it to someone. It stops showing the van when the trip ends. */
                    'map_url' => $trip ? url("/t/{$trip->public_id}").'?stop='.$seat->boardsAt() : null,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Her route on a map, whether or not a van is on the road.
     *
     * One entry for each seat she holds, reserved or paid. The home screen draws it under her own position,
     * so she can see her stop and the road the van takes. Uses no paid service: the road was traced once and stored.
     */
    public function route(Request $request, TripProgress $progress): JsonResponse
    {
        $rider = $this->rider($request);

        $seats = $rider === null ? collect() : Seat::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])
            ->with(['run.route.stops'])
            ->get()
            ->sortBy(fn (Seat $seat) => $seat->run->departureTime())
            ->values();

        return response()->json([
            /** @var list<array{seat_id: int, route: string, direction: 'outbound'|'return', my_stop: string, my_stop_id: int, my_time: string|null, off_stop_id: int, path: list<array{0: float, 1: float}>|null, stops: list<array{id: int, name: string, landmark: string|null, time: string|null, latitude: float|null, longitude: float|null, reached: bool}>}> */
            'routes' => $seats->map(function (Seat $seat) use ($progress) {
                $state = $progress->handle($seat->run, null, $seat->boardsAt());

                return [
                    'seat_id' => $seat->id,
                    'route' => $seat->run->route->name,
                    /** @var 'outbound'|'return' */
                    'direction' => $seat->run->direction->value,
                    /** Where she gets on */
                    'my_stop' => $seat->run->route->stops->firstWhere('id', $seat->boardsAt())->name,
                    'my_stop_id' => $seat->boardsAt(),
                    /** Where she gets off */
                    'off_stop_id' => $seat->getsOffAt(),
                    /** @var string|null When the van is due at her stop, "06:38" */
                    'my_time' => $seat->pickupTime(),
                    /** @var list<array{0: float, 1: float}>|null The road the run follows, as [latitude, longitude]. Null until traced: then join the stops. */
                    'path' => $state['path'],
                    /** @var list<array{id: int, name: string, landmark: string|null, time: string|null, latitude: float|null, longitude: float|null, reached: bool}> */
                    'stops' => $state['stops'],
                ];
            })->all(),
        ]);
    }

    /**
     * Her past trips, newest first, 20 a page.
     *
     * Each one says whether the mate marked her on board, not at the stop, or neither,
     * or that she had said she would not ride that day.
     */
    public function history(Request $request, RiderTripHistory $history): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $rider = $this->rider($request);

        // Someone with no rider record gets an empty first page, so the reply always has one shape.
        $page = $history->handle($rider->id ?? 0);

        return response()->json([
            /** @var list<array{date: string, route: string, direction: 'outbound'|'return', car_number: string, my_stop: string|null, status: 'running'|'ended', outcome: 'picked_up'|'missed'|'not_riding'|'not_marked', picked_up_at: string|null, alighted_at: string|null, alighted_at_stop: string|null, started_at: string, ended_at: string|null}> */
            'trips' => $page->items(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /**
     * She has got down.
     *
     * Records where and when, for today's trip on that run. Saying it twice changes nothing.
     * If the mate forgot to mark her on board, this marks her on board too.
     */
    public function alight(Request $request, RecordAlighting $record): JsonResponse
    {
        $data = $request->validate(['run_id' => ['required', 'integer']]);
        $rider = $this->riderOrFail($request);

        $seat = Seat::query()->where('rider_id', $rider->id)->where('run_id', $data['run_id'])->where('status', SeatStatus::Active)->first();
        $trip = $seat ? Trip::query()->where('run_id', $seat->run_id)->where('date', today()->toDateString())->first() : null;

        if ($trip === null) {
            throw new DomainException('Your van has not set off today.');
        }

        $pickup = $record->handle($trip, $rider->id, $seat->boardsAt());

        return response()->json([
            'alighted_at' => $pickup->alighted_at->toIso8601String(),
            /** Where she got down */
            'stop' => $pickup->alightedStop?->name,
        ]);
    }
}
