<?php

namespace Modules\Trips\Events;

/** The van is nearly at a stop where someone gets on or off. Goes to the driver and the mate. */
class CrewApproaching extends TripEvent
{
    public function name(): string
    {
        return 'crew.approaching';
    }
}
