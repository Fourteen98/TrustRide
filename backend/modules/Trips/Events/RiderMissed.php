<?php

namespace Modules\Trips\Events;

class RiderMissed extends TripEvent
{
    public function name(): string
    {
        return 'rider.missed';
    }
}
