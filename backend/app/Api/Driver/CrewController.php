<?php

namespace App\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Models\Run;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Actions\MarkPickup;
use Modules\Trips\Actions\RecordPings;
use Modules\Trips\Actions\StartTrip;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Models\Trip;
use Modules\Trips\Queries\CrewRuns;
use Modules\Trips\Queries\RidersOnTrip;

/**
 * The driver's and the mate's side. Both see the same runs: the driver's own, and for a mate, his driver's.
 * A run id in a request is always checked against that list, so a phone can only touch its own van.
 */
class CrewController
{
    public function __construct(private CrewRuns $runs, private RidersOnTrip $riders) {}

    /**
     * Today's runs with the riders at each stop.
     *
     * One call for the whole crew screen. Ask again after every action, and every so often while a trip runs.
     */
    public function today(Request $request): JsonResponse
    {
        return response()->json([
            'runs' => $this->runs->today($request->user())->map(fn (Run $run) => $this->describe($run))->values()->all(),
            /** Seconds between positions the phone should send while a trip is running */
            'ping_interval_seconds' => (int) config('trips.ping_interval_seconds'),
        ]);
    }

    /**
     * Start today's trip.
     *
     * Everyone riding today is told the van has set off. Pressing it twice is harmless.
     */
    public function start(Request $request, StartTrip $start): JsonResponse
    {
        $request->validate(['run_id' => ['required', 'integer']]);
        $run = $this->run($request);
        $start->handle($run, $request->user());

        return response()->json(['run' => $this->describe($run)], 201);
    }

    /**
     * End today's trip.
     *
     * The van's position stops being shown to riders.
     */
    public function end(Request $request, EndTrip $end): JsonResponse
    {
        $request->validate(['run_id' => ['required', 'integer']]);
        $run = $this->run($request);

        if ($trip = $this->trip($run)) {
            $end->handle($trip, $request->user());
        }

        return response()->json(['run' => $this->describe($run)]);
    }

    /**
     * Mark a rider picked up or not at her stop.
     *
     * Can be changed while the trip runs. Marking anyone at a stop also moves the van to that stop.
     */
    public function pickup(Request $request, MarkPickup $mark): JsonResponse
    {
        $data = $request->validate([
            'run_id' => ['required', 'integer'],
            'rider_id' => ['required', 'integer'],
            'status' => ['required', Rule::enum(PickupStatus::class)],
        ]);

        $run = $this->run($request);
        $trip = $this->trip($run) ?? throw new \DomainException('Start the trip first.');

        $mark->handle($trip, $data['rider_id'], PickupStatus::from($data['status']), $request->user());

        return response()->json(['run' => $this->describe($run)]);
    }

    /**
     * Send the van's position.
     *
     * Up to 200 positions at once, so a phone that lost its network can send what it saved.
     * Send from both the driver's and the mate's phone. The newest one wins.
     */
    public function location(Request $request, RecordPings $record): JsonResponse
    {
        $data = $request->validate([
            'run_id' => ['required', 'integer'],
            'pings' => ['required', 'array', 'min:1', 'max:200'],
            'pings.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            /** Metres */
            'pings.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            /** When the phone took the reading, ISO 8601 */
            'pings.*.recorded_at' => ['required', 'date'],
        ]);

        $trip = $this->trip($this->run($request));

        return response()->json([
            /** How many were kept. 0 with `running: false` means stop sending. */
            'kept' => $trip?->isRunning() ? $record->handle($trip, $request->user(), $data['pings']) : 0,
            'running' => (bool) $trip?->isRunning(),
        ]);
    }

    private function run(Request $request): Run
    {
        return $this->runs->find($request->user(), $request->integer('run_id'));
    }

    private function trip(Run $run): ?Trip
    {
        return Trip::query()->where('run_id', $run->id)->where('date', today()->toDateString())->with('pickups')->first();
    }

    private function describe(Run $run): array
    {
        $trip = $this->trip($run);

        return [
            'run_id' => $run->id,
            'route' => $run->route->name,
            /** @var 'outbound'|'return' */
            'direction' => $run->direction->value,
            /** "06:30" */
            'departs_at' => $run->departureTime(),
            'car_number' => $run->vehicle->registration_number,
            'driver_name' => $run->driver->name,
            /** @var 'not_started'|'running'|'ended' */
            'status' => $trip === null ? 'not_started' : $trip->status->value,
            'started_at' => $trip?->started_at->toIso8601String(),
            /** Stops in the order the van reaches them, each with who gets on there today */
            'stops' => $this->riders->handle($run, $trip)->all(),
        ];
    }
}
