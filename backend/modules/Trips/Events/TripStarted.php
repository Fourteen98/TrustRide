<?php

namespace Modules\Trips\Events;

class TripStarted extends TripEvent
{
    public function name(): string
    {
        return 'trip.started';
    }
}
