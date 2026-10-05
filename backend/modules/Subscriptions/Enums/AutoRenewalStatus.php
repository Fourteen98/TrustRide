<?php

namespace Modules\Subscriptions\Enums;

enum AutoRenewalStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for her to approve',
            self::Active => 'On',
            self::Off => 'Off',
        };
    }
}
