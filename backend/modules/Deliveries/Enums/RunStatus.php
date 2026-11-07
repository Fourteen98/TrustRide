<?php

namespace Modules\Deliveries\Enums;

enum RunStatus: string
{
    case Planned = 'planned';
    case Out = 'out';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Out => 'Out',
            self::Done => 'Done',
        };
    }
}
