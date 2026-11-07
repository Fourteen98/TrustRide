<?php

namespace Modules\Trips\Events;

/** The van is nearly at a stop. Goes to the riders getting on there. */
class VanApproaching extends TripEvent
{
    public function name(): string
    {
        return 'van.approaching';
    }
}
