<?php

namespace Modules\Trips\Events;

class TripEnded extends TripEvent
{
    public function name(): string
    {
        return 'trip.ended';
    }
}
