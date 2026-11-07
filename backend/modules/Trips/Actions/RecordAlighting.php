<?php

namespace Modules\Trips\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Events\RiderAlighted;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/**
 * She tells the app she has got down. The stop is the last one the van reached, or the end of the route
 * once the trip is over. Saying it twice changes nothing.
 *
 * If the mate forgot to mark her on board, getting down says she rode, so she is marked on board too.
 * If the mate marked her as not at the stop, that is a disagreement for Help, not something the app settles.
 */
class RecordAlighting
{
    public function handle(Trip $trip, int $riderId, int $stopId): TripPickup
    {
        if (! $trip->date->isToday()) {
            throw new DomainException('You can only say you got down on the day of the trip.');
        }

        return DB::transaction(function () use ($trip, $riderId, $stopId) {
            $pickup = TripPickup::query()->where('trip_id', $trip->id)->where('rider_id', $riderId)->lockForUpdate()->first();

            if ($pickup?->status === PickupStatus::Missed) {
                throw new DomainException('The mate marked you as not at your stop. If that is wrong, tell us under Help.');
            }

            if ($pickup?->alighted_at !== null) {
                return $pickup;
            }

            $trip->loadMissing('run.route.stops');
            $last = $trip->stopsInOrder()->last();
            $at = $trip->isRunning() ? ($trip->reached_stop_id ?? $stopId) : $last->id;

            $pickup = TripPickup::query()->updateOrCreate(
                ['trip_id' => $trip->id, 'rider_id' => $riderId],
                [
                    'stop_id' => $pickup->stop_id ?? $stopId,
                    'status' => PickupStatus::PickedUp,
                    'marked_at' => $pickup->marked_at ?? now(),
                    'alighted_at' => now(),
                    'alighted_stop_id' => $at,
                ],
            );

            RiderAlighted::dispatch($trip, [], ['rider_id' => $riderId, 'stop_id' => $at]);

            return $pickup;
        });
    }
}
