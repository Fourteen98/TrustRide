<?php

namespace Modules\Trips\Queries;

use Modules\Network\Models\Stop;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Models\Run;
use Modules\Trips\Models\Trip;

/**
 * Where a trip has got to, as plain data anyone may see: the stops in order, which are behind
 * the van, the van's position, and how many stops away a given stop is. No rider is named.
 * The rider screen, the share page and the console map all read this.
 */
class TripProgress
{
    public function __construct(private EstimateArrival $arrival) {}

    /**
     * @return array{status: string, stops: list<array{id: int, name: string, landmark: ?string, time: ?string, latitude: ?float, longitude: ?float, reached: bool}>, stops_away: ?int, path: ?list<array{0: float, 1: float}>, position: ?array{latitude: float, longitude: float, at: string, live: bool, heading: ?float}, minutes_away: ?int}
     */
    public function handle(Run $run, ?Trip $trip, ?int $forStopId = null): array
    {
        $probe = $trip ?? (new Trip)->setRelation('run', $run);
        $running = $trip?->isRunning() ?? false;
        $reached = $running ? $probe->orderOf($trip->reached_stop_id) : null;
        $mine = $probe->orderOf($forStopId);

        return [
            'status' => $trip === null ? 'not_started' : $trip->status->value,
            'stops' => $probe->stopsInOrder()->map(fn (Stop $stop, int $index) => [
                'id' => $stop->id,
                'name' => $stop->name,
                'landmark' => $stop->landmark,
                'time' => $run->timeAtStop($stop->id),
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'reached' => $reached !== null && $index <= $reached,
            ])->all(),
            // The road the run follows, in the direction of travel. Null until the route has been traced,
            // and then maps join the stops with straight lines.
            'path' => $this->path($run),
            // 0 means the van is at her stop or has passed it. Before the first stop is reached it counts from the start.
            'stops_away' => $running && $mine !== null ? max(0, $mine - ($reached ?? -1)) : null,
            // The position is only given out while the trip is running.
            'position' => $running && $trip->latitude !== null ? [
                'latitude' => $trip->latitude,
                'longitude' => $trip->longitude,
                'at' => $trip->located_at->toIso8601String(),
                'live' => $trip->hasFreshPosition(),
                'heading' => $trip->heading(),
            ] : null,
            // Minutes until the van reaches the given stop in today's traffic. Null without a Google key,
            // when the van's position isn't live, or when the stop is already behind it.
            'minutes_away' => $running && $forStopId !== null ? $this->arrival->minutes($trip, $forStopId) : null,
        ];
    }

    /** @return list<array{0: float, 1: float}>|null */
    private function path(Run $run): ?array
    {
        $path = $run->route->roadPath();

        return $path && $run->direction === Direction::Return ? array_reverse($path) : $path;
    }
}
