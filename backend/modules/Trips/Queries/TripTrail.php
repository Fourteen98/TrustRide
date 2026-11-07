<?php

namespace Modules\Trips\Queries;

use Modules\Trips\Models\LocationPing;
use Modules\Trips\Models\Trip;

/**
 * The road the van has actually driven so far today, from both phones merged by time.
 * Only while the trip runs. At most about 300 points, thinned evenly, always ending at the van.
 */
class TripTrail
{
    /** @return list<array{0: float, 1: float}> */
    public function handle(?Trip $trip): array
    {
        if (! $trip?->isRunning()) {
            return [];
        }

        $points = LocationPing::query()->where('trip_id', $trip->id)->orderBy('recorded_at')->get(['latitude', 'longitude']);
        $step = max(1, (int) ceil($points->count() / 300));

        return $points
            ->filter(fn ($point, int $index) => $index % $step === 0 || $index === $points->count() - 1)
            ->map(fn (LocationPing $point) => [round($point->latitude, 6), round($point->longitude, 6)])
            ->values()
            ->all();
    }
}
