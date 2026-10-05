<?php

namespace Modules\Trips\Actions;

use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Events\TripEnded;
use Modules\Trips\Models\Trip;

/** The van has arrived. The position stops being given out, and the share link stops showing it. */
class EndTrip
{
    public function handle(Trip $trip, ?Account $by = null, ?Staff $staff = null): Trip
    {
        if (! $trip->isRunning()) {
            return $trip;
        }

        $trip->update([
            'status' => TripStatus::Ended,
            'ended_at' => now(),
            'ended_by_account' => $by?->id,
            'ended_by_staff' => $staff?->id,
        ]);

        TripEnded::dispatch($trip);

        return $trip;
    }
}
