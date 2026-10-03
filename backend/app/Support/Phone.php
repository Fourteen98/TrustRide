<?php

namespace App\Support;

use InvalidArgumentException;

final class Phone
{
    /**
     * Turn a Ghanaian number into E.164. Accepts 0241234567, 233241234567 and +233241234567.
     */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233'.substr($digits, 1);
        }

        throw new InvalidArgumentException('Enter a valid Ghana phone number.');
    }

    public static function isValid(string $phone): bool
    {
        try {
            self::normalise($phone);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
