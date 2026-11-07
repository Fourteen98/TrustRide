<?php

namespace Modules\Trips\Actions;

use App\Support\Maps\Distance;
use App\Support\Recipient;
use Modules\Drivers\Models\Mate;
use Modules\Subscriptions\Queries\TodaysRiders;
use Modules\Trips\Events\CrewApproaching;
use Modules\Trips\Events\StopApproaching;
use Modules\Trips\Events\VanApproaching;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/**
 * Tells people the van is nearly at its next stop: the riders getting on there to be ready at the stop, the riders on
 * board who get off there to get ready, and the driver and the mate how many get on and off.
 *
 * Called with each new position. Each stop is announced once a trip, when the van first comes within
 * `trips.approach_metres` of it. The first stop is never announced: "the van has set off" covers it.
 * Nothing is sent for a stop where nobody gets on or off.
 */
class AnnounceNextStop
{
    public function __construct(private TodaysRiders $riders) {}

    /** @return bool whether a stop was announced */
    public function handle(Trip $trip, float $latitude, float $longitude): bool
    {
        $stops = $trip->stopsInOrder();
        $from = max($trip->orderOf($trip->reached_stop_id) ?? 0, $trip->orderOf($trip->approached_stop_id) ?? 0) + 1;
        $radius = (int) config('trips.approach_metres');

        $index = null;

        for ($i = $from; $i < $stops->count(); $i++) {
            $stop = $stops[$i];

            if ($stop->latitude !== null && Distance::metres($latitude, $longitude, $stop->latitude, $stop->longitude) <= $radius) {
                $index = $i;

                break;
            }
        }

        if ($index === null) {
            return false;
        }

        $stop = $stops[$index];
        $trip->update(['approached_stop_id' => $stop->id]);

        $pickups = TripPickup::query()->where('trip_id', $trip->id)->get()->keyBy('rider_id');
        $today = $this->riders->handle($trip->run, $trip->date)->reject(fn (array $rider) => $rider['absent']);

        // Getting on: due here and not yet marked by the mate.
        $boarding = $today->filter(fn (array $rider) => $rider['stop_id'] === $stop->id && ! isset($pickups[$rider['rider_id']]));
        // Getting off: on board and hasn't already said she got down.
        $alighting = $today->filter(function (array $rider) use ($stop, $pickups) {
            $pickup = $pickups[$rider['rider_id']] ?? null;

            return $rider['off_stop_id'] === $stop->id && $pickup?->status->value === 'picked_up' && $pickup->alighted_at === null;
        });

        if ($boarding->isEmpty() && $alighting->isEmpty()) {
            return true;
        }

        $people = fn ($riders) => (int) $riders->sum(fn (array $rider) => 1 + $rider['on_plan'] + $rider['paying_fare']);
        $on = $people($boarding);
        $off = $people($alighting);
        $extra = [
            'stop' => $stop->name,
            'stop_id' => $stop->id,
            'getting_on' => $on,
            'getting_off' => $off,
            // Ready to read out: "2 getting on, 1 getting off", leaving out a side with nobody.
            'moves' => implode(', ', array_filter([$on > 0 ? "{$on} getting on" : null, $off > 0 ? "{$off} getting off" : null])),
        ];
        $recipient = fn (array $rider) => new Recipient('rider', $rider['phone'], $rider['channel'], $rider['name']);

        if ($boarding->isNotEmpty()) {
            VanApproaching::dispatch($trip, $boarding->map($recipient)->values()->all(), $extra);
        }

        if ($alighting->isNotEmpty()) {
            StopApproaching::dispatch($trip, $alighting->map($recipient)->values()->all(), $extra);
        }

        CrewApproaching::dispatch($trip, $this->crew($trip), $extra);

        return true;
    }

    /** @return list<Recipient> the driver and his active mates */
    private function crew(Trip $trip): array
    {
        $driver = $trip->run->driver->loadMissing('account', 'mates.account');
        $people = $driver->account ? [new Recipient('driver', $driver->account->phone, 'push', $driver->name)] : [];

        $driver->mates->where('is_active', true)->each(function (Mate $mate) use (&$people) {
            if ($mate->account) {
                $people[] = new Recipient('mate', $mate->account->phone, 'push', $mate->name);
            }
        });

        return $people;
    }
}
