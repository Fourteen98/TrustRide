<?php

namespace Modules\Trips\Enums;

enum PickupStatus: string
{
    case PickedUp = 'picked_up';
    case Missed = 'missed';

    public function label(): string
    {
        return $this === self::PickedUp ? 'Picked up' : 'Not at the stop';
    }
}
