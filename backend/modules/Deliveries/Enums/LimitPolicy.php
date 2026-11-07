<?php

namespace Modules\Deliveries\Enums;

/** What happens when a business has used its month's deliveries. Brief: "build so any of these can be chosen". */
enum LimitPolicy: string
{
    /** Keep taking orders. Each one over is charged at the plan's overage fee on the month's invoice. */
    case Overage = 'overage';
    /** Refuse new orders until the next month. */
    case Stop = 'stop';

    public function label(): string
    {
        return match ($this) {
            self::Overage => 'Keep going, charge each one over',
            self::Stop => 'Stop taking orders until next month',
        };
    }
}
