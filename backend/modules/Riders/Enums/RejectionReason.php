<?php

namespace Modules\Riders\Enums;

/** Why we couldn't take her. Always about what we can serve, never about the person. */
enum RejectionReason: string
{
    case NoCapacity = 'no_capacity';
    case RouteNotServed = 'route_not_served';
    case TimeNotServed = 'time_not_served';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NoCapacity => 'No seats left on that run',
            self::RouteNotServed => 'We don\'t serve that route yet',
            self::TimeNotServed => 'We don\'t have a van at that time',
            self::Other => 'Other',
        };
    }
}
