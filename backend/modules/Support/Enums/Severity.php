<?php

namespace Modules\Support\Enums;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low. Annoying, nobody at risk',
            self::Medium => 'Medium. Service failed, or money is involved',
            self::High => 'High. Someone\'s safety',
        };
    }
}
