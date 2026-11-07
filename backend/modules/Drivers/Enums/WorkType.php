<?php

namespace Modules\Drivers\Enums;

/** The kinds of work a driver does for Aveo. Ride is the shuttle; Ox is parcel delivery, which comes later. */
enum WorkType: string
{
    case Ride = 'ride';
    case Ox = 'ox';

    public function label(): string
    {
        return match ($this) {
            self::Ride => 'Aveo Ride',
            self::Ox => 'Aveo Ox',
        };
    }
}
