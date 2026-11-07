<?php

namespace Modules\Drivers\Enums;

/**
 * How far a driver is on the way from a daily contract to an Aveo driver-partner. Separate from his status, which
 * says whether he may drive at all. Monthly assessments decide who moves up; an operator moves him.
 */
enum DriverStage: string
{
    /** Engaged day by day. Where every driver starts. */
    case DailyContract = 'daily_contract';
    /** Has at least one monthly assessment on record and is being considered. */
    case Assessed = 'assessed';
    /** A long-term Aveo driver-partner, with his own car or an investor-funded one. */
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::DailyContract => 'Daily contract',
            self::Assessed => 'Assessed',
            self::Partner => 'Driver-partner',
        };
    }
}
