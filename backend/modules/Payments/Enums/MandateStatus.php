<?php

namespace Modules\Payments\Enums;

enum MandateStatus: string
{
    case Pending = 'pending'; // asked for, waiting for her to approve
    case Active = 'active';
    case Failed = 'failed'; // she declined, or it timed out
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Active => 'Active',
            self::Failed => 'Not approved',
            self::Cancelled => 'Cancelled',
        };
    }
}
