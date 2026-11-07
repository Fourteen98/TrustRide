<?php

namespace Modules\Trips\Actions;

use App\Support\Maps\Distance;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Network\Models\Stop;
use Modules\Trips\Models\LocationPing;
use Modules\Trips\Models\Trip;
use Throwable;

/**
 * Takes a batch of positions from a crew phone. A phone that lost its network sends everything it
 * saved, so a batch can be minutes old and out of order.
 *
 * Every usable ping is kept as history. The trip itself keeps only the newest, whichever phone
 * sent it, so the van stays on the map if one phone dies. Passing within reach of a stop
 * marks that stop as reached. The van never goes backwards along the route.
 * Coming near the next stop tells the people getting on and off there.
 */
class RecordPings
{
    public function __construct(private AnnounceNextStop $announce) {}

    /**
     * @param  list<array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null, recorded_at: string}>  $pings
     * @return int how many were kept
     */
    public function handle(Trip $trip, Account $from, array $pings): int
    {
        if (! $trip->isRunning()) {
            throw new DomainException('The trip is not running, so the position is not needed.');
        }

        $usable = collect($pings)
            ->map(function (array $ping) {
                try {
                    return [...$ping, 'recorded_at' => CarbonImmutable::parse($ping['recorded_at'])];
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            // A phone with a wrong clock would otherwise put the van in the future or before it left.
            ->filter(fn (array $ping) => $ping['recorded_at']->gte($trip->started_at->subMinute()) && $ping['recorded_at']->lte(now()->addMinutes(2)))
            ->filter(fn (array $ping) => abs((float) $ping['latitude']) <= 90 && abs((float) $ping['longitude']) <= 180)
            ->sortBy('recorded_at')
            ->values();

        if ($usable->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($trip, $from, $usable) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $trip->load('run.route.stops');

            LocationPing::query()->insert($usable->map(fn (array $ping) => [
                'trip_id' => $trip->id,
                'account_id' => $from->id,
                'latitude' => $ping['latitude'],
                'longitude' => $ping['longitude'],
                'accuracy' => isset($ping['accuracy']) ? min(65535, (int) round((float) $ping['accuracy'])) : null,
                'recorded_at' => $ping['recorded_at']->utc()->toDateTimeString(),
                'created_at' => now()->utc()->toDateTimeString(),
            ])->all());

            $reached = $trip->orderOf($trip->reached_stop_id) ?? -1;
            $radius = (int) config('trips.stop_radius_metres');
            $stops = $trip->stopsInOrder();

            foreach ($usable as $ping) {
                $stops->each(function (Stop $stop, int $index) use (&$reached, $ping, $radius) {
                    if ($index > $reached && $stop->latitude !== null
                        && Distance::metres((float) $ping['latitude'], (float) $ping['longitude'], $stop->latitude, $stop->longitude) <= $radius) {
                        $reached = $index;
                    }
                });
            }

            $newest = $usable->last();
            $changes = $reached >= 0 ? ['reached_stop_id' => $stops[$reached]->id] : [];

            if ($trip->located_at === null || $newest['recorded_at']->gt($trip->located_at)) {
                $changes += ['latitude' => $newest['latitude'], 'longitude' => $newest['longitude'], 'located_at' => $newest['recorded_at']];
            }

            $trip->update($changes);

            // Only a fresh position: a batch saved while offline says where the van was, not where it is.
            if ($newest['recorded_at']->gte(now()->subMinutes(2))) {
                $this->announce->handle($trip, (float) $newest['latitude'], (float) $newest['longitude']);
            }

            return $usable->count();
        });
    }
}
