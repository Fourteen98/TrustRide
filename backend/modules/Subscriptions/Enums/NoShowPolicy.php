<?php

namespace Modules\Subscriptions\Enums;

/** What happens to a day the rider did not use. Set per plan. */
enum NoShowPolicy: string
{
    case LoseDay = 'lose_day';
    case CreditWithNotice = 'credit_with_notice';
    case CreditAlways = 'credit_always';

    public function label(): string
    {
        return match ($this) {
            self::LoseDay => 'She loses the day',
            self::CreditWithNotice => 'Credited only if she told us in time',
            self::CreditAlways => 'Always credited',
        };
    }
}
