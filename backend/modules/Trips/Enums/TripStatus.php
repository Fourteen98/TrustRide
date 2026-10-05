<?php

namespace Modules\Trips\Enums;

enum TripStatus: string
{
    case Running = 'running';
    case Ended = 'ended';

    public function label(): string
    {
        return $this === self::Running ? 'On the road' : 'Ended';
    }
}
