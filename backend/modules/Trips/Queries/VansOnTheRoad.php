<?php

namespace Modules\Trips\Queries;

use Illuminate\Support\Collection;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Models\Trip;

/**
 * Every Aveo van on the road today, where it is and which way it faces, for drawing on a crew's map.
 * Nothing that names the van, its crew or its riders: only the position.
 */
class VansOnTheRoad
{
    /** @return Collection<int, array{key: string, latitude: float, longitude: float, heading: ?float, live: bool}> */
    public function handle(?int $exceptRunId = null): Collection
    {
        return Trip::query()
            ->where('date', today()->toDateString())
            ->where('status', TripStatus::Running)
            ->whereNotNull('latitude')
            ->when($exceptRunId, fn ($query) => $query->where('run_id', '!=', $exceptRunId))
            ->get()
            ->map(fn (Trip $trip) => [
                // Only so a map can follow the same van from one update to the next. It names nothing.
                'key' => $trip->public_id,
                'latitude' => $trip->latitude,
                'longitude' => $trip->longitude,
                'heading' => $trip->heading(),
                'live' => $trip->hasFreshPosition(),
            ])
            ->values();
    }
}
