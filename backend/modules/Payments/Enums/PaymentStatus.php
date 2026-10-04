<?php

namespace Modules\Payments\Enums;

enum PaymentStatus: string
{
    case Initiated = 'initiated'; // asked for, waiting for the rider to approve
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Waiting',
            self::Success => 'Paid',
            self::Failed => 'Failed',
        };
    }
}
