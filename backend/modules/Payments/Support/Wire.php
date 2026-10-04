<?php

namespace Modules\Payments\Support;

/** Small helpers for talking to a gateway whose replies are not consistent. */
final class Wire
{
    /**
     * Case-insensitive array lookup. Hubtel's docs show camelCase keys, and its live callbacks
     * arrive in PascalCase. Reading either spelling stops a callback being dropped over a capital letter.
     */
    public static function pick(mixed $array, string $key): mixed
    {
        if (! is_array($array)) {
            return null;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach ($array as $name => $value) {
            if (is_string($name) && strcasecmp($name, $key) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** First and last two characters of a secret, so a log proves it is set without leaking it. */
    public static function mask(?string $secret): string
    {
        $length = strlen((string) $secret);

        return match (true) {
            $length === 0 => '(empty)',
            $length <= 6 => str_repeat('*', $length),
            default => substr($secret, 0, 2).str_repeat('*', $length - 4).substr($secret, -2)." (len {$length})",
        };
    }

    /** +233241234567 as Hubtel wants it: 233241234567. */
    public static function msisdn(string $phone): string
    {
        return ltrim($phone, '+');
    }
}
