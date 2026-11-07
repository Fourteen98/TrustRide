<?php

namespace Modules\Trips\Events;

class RiderAlighted extends TripEvent
{
    public function name(): string
    {
        return 'rider.alighted';
    }
}
