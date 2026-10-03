<?php

namespace App\Support;

/** Money is stored in pesewas as whole numbers. GHS 60 is 6000. */
final class Money
{
    public static function format(int $pesewas): string
    {
        return 'GHS '.number_format($pesewas / 100, $pesewas % 100 === 0 ? 0 : 2);
    }

    public static function toPesewas(int|float|string $cedis): int
    {
        return (int) round(((float) $cedis) * 100);
    }

    public static function toCedis(int $pesewas): float
    {
        return $pesewas / 100;
    }
}
