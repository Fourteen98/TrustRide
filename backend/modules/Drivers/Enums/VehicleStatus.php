<?php

namespace Modules\Drivers\Enums;

enum VehicleStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case OffRoad = 'off_road';

    public function label(): string
    {
        return match ($this) {
            self::OffRoad => 'Off the road',
            default => ucfirst($this->value),
        };
    }
}
