<?php

namespace Modules\Support\Enums;

/** What the person says the problem is. She picks one when she first writes. */
enum CaseCategory: string
{
    case VanLate = 'van_late';
    case VanMissing = 'van_missing';
    case SeatTaken = 'seat_taken';
    case Crew = 'crew';
    case Payment = 'payment';
    case Safety = 'safety';
    case Other = 'other';

    /** Said the way the person would say it. Shown in the app and in the console. */
    public function label(): string
    {
        return match ($this) {
            self::VanLate => 'The van is late',
            self::VanMissing => 'The van didn\'t come',
            self::SeatTaken => 'My seat was taken',
            self::Crew => 'A problem with the driver or mate',
            self::Payment => 'A payment problem',
            self::Safety => 'I don\'t feel safe',
            self::Other => 'Something else',
        };
    }

    /** These become an incident the moment they arrive, and the operator on call is texted. */
    public function isUrgent(): bool
    {
        return $this === self::Safety;
    }
}
