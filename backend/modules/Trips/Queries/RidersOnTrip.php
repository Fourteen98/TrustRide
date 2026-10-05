<?php

namespace Modules\Trips\Queries;

use Illuminate\Support\Collection;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders;
use Modules\Trips\Models\Trip;

/**
 * Today's riders on a run, grouped by stop in the order the van reaches them, with what the mate
 * has marked for each. Works before the trip starts too, when nothing is marked yet.
 */
class RidersOnTrip
{
    public function __construct(private TodaysRiders $riders) {}

    /**
     * @return Collection<int, array{stop_id: int, name: string, landmark: ?string, time: ?string, reached: bool, riders: list<array{rider_id: int, name: string, absent: bool, pickup: 'picked_up'|'missed'|null}>}>
     */
    public function handle(Run $run, ?Trip $trip): Collection
    {
        $run->loadMissing('route.stops');
        $riders = $this->riders->handle($run)->groupBy('stop_id');
        $pickups = $trip ? $trip->pickups->keyBy('rider_id') : collect();

        $probe = $trip ?? (new Trip)->setRelation('run', $run);
        $reached = $trip ? $probe->orderOf($trip->reached_stop_id) : null;

        return $probe->stopsInOrder()
            ->map(fn ($stop, int $index) => [
                'stop_id' => $stop->id,
                'name' => $stop->name,
                'landmark' => $stop->landmark,
                'time' => $run->timeAtStop($stop->id),
                'reached' => $reached !== null && $index <= $reached,
                'riders' => ($riders[$stop->id] ?? collect())->map(fn (array $rider) => [
                    'rider_id' => $rider['rider_id'],
                    'name' => $rider['name'],
                    'absent' => $rider['absent'],
                    'pickup' => ($pickups[$rider['rider_id']] ?? null)?->status->value,
                ])->values()->all(),
            ])
            // The last stop is where everyone gets off. Nobody is picked up there.
            ->filter(fn (array $stop, int $index) => $stop['riders'] !== [] || $index < $probe->stopsInOrder()->count() - 1)
            ->values();
    }

    /** True if this rider is due on the run today and hasn't said she won't ride. */
    public function expects(Run $run, int $riderId): ?array
    {
        return $this->riders->handle($run)->first(fn (array $rider) => $rider['rider_id'] === $riderId && ! $rider['absent']);
    }
}
