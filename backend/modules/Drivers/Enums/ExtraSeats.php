<?php

namespace Modules\Drivers\Enums;

/**
 * Whether a rider may hold seats on this van for people travelling with her, and how they pay.
 * Set per van in the console, for Aveo's own vans and partners' alike.
 */
enum ExtraSeats: string
{
    /** Only subscribers. */
    case Off = 'off';

    /** She holds seats for a day. Each person pays the usual fare to the mate. */
    case Fare = 'fare';

    /** She adds seats to her plan and pays for them with it, for every day of the plan. */
    case Plan = 'plan';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Fare => 'They pay the fare to the mate',
            self::Plan => 'Added to her plan',
        };
    }
}
