<?php

namespace Modules\Subscriptions\Enums;

enum SeatStatus: string
{
    case Reserved = 'reserved'; // approved, not yet paid
    case Active = 'active';
    case Released = 'released';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
