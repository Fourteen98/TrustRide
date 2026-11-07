<?php

namespace Modules\Trips\Queries;

use App\Support\Maps\GoogleRoutes;
use Illuminate\Support\Facades\Cache;
use Modules\Network\Models\Stop;
use Modules\Trips\Models\Trip;

/**
 * Minutes until the van reaches a stop, in today's traffic, from where it is now and through the
 * stops it still has to make first.
 *
 * Only asked when it means something: the trip is running, the van's position is live, and the stop
 * is still ahead. The answer is shared for a minute by everyone looking at that van and stop, so
 * a hundred riders refreshing cost one call. Without a Google key it is always null.
 */
class EstimateArrival
{
    public function minutes(Trip $trip, int $stopId): ?int
    {
        if (! GoogleRoutes::configured() || ! $trip->isRunning() || ! $trip->hasFreshPosition()) {
            return null;
        }

        $mine = $trip->orderOf($stopId);
        $reached = $trip->orderOf($trip->reached_stop_id) ?? -1;

        if ($mine === null || $mine <= $reached) {
            return null;
        }

        $via = $trip->stopsInOrder()->slice($reached + 1, $mine - $reached)->values();

        if ($via->contains(fn (Stop $stop) => $stop->latitude === null)) {
            return null;
        }

        $seconds = Cache::remember(
            "trips:eta:{$trip->id}:{$stopId}",
            (int) config('aveo.maps.eta_cache_seconds'),
            fn () => GoogleRoutes::seconds([[$trip->latitude, $trip->longitude], ...$via->map(fn (Stop $stop) => [$stop->latitude, $stop->longitude])->all()]),
        );

        return $seconds === null ? null : max(1, (int) ceil($seconds / 60));
    }
}
