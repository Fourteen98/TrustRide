<?php

namespace Modules\Subscriptions\Enums;

enum Direction: string
{
    case Outbound = 'outbound';
    case Return = 'return';

    public function label(): string
    {
        return $this === self::Outbound ? 'To the destination' : 'Back from the destination';
    }
}
