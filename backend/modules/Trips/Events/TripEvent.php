<?php

namespace Modules\Trips\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;
use Modules\Trips\Models\Trip;

/** Shared by the trip events. $people are who it concerns, and $extra is what one event adds to the payload. */
abstract class TripEvent extends BaseDomainEvent
{
    public int $tripId;

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param  list<Recipient>  $people
     * @param  array<string, mixed>  $extra
     */
    public function __construct(Trip $trip, private array $people = [], array $extra = [])
    {
        $trip->loadMissing(['run.route', 'run.driver', 'run.vehicle']);

        $this->tripId = $trip->id;
        $this->data = [
            'trip_id' => $trip->id,
            'run_id' => $trip->run_id,
            'date' => $trip->date->toDateString(),
            'route' => $trip->run->route->name,
            'departs_at' => $trip->run->departureTime(),
            'driver_name' => $trip->run->driver->name,
            'car_number' => $trip->run->vehicle->registration_number,
            'car_colour' => $trip->run->vehicle->colour,
            ...$extra,
        ];
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return $this->people;
    }
}
