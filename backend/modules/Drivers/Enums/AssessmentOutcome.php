<?php

namespace Modules\Drivers\Enums;

enum AssessmentOutcome: string
{
    case Met = 'met';
    case NotMet = 'not_met';

    public function label(): string
    {
        return $this === self::Met ? 'Met' : 'Not met';
    }
}
