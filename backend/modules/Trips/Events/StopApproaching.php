<?php

namespace Modules\Trips\Events;

/** The van is nearly at a stop. Goes to the riders on board who get off there. */
class StopApproaching extends TripEvent
{
    public function name(): string
    {
        return 'stop.approaching';
    }
}
