<?php

namespace Modules\Trips\Console;

use Illuminate\Console\Command;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Models\Trip;

class EndStaleTrips extends Command
{
    protected $signature = 'trips:end-stale';

    protected $description = 'End trips from before today that nobody ended, so a van is never shown as on the road overnight';

    public function handle(EndTrip $end): int
    {
        $trips = Trip::query()->where('status', TripStatus::Running)->where('date', '<', today()->toDateString())->get();
        $trips->each(fn (Trip $trip) => $end->handle($trip));

        $this->info("Ended {$trips->count()} trips.");

        return self::SUCCESS;
    }
}
