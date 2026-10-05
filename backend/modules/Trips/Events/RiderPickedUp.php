<?php

namespace Modules\Trips\Events;

class RiderPickedUp extends TripEvent
{
    public function name(): string
    {
        return 'rider.picked_up';
    }
}
