<?php

namespace App\Support\Maps;

/**
 * Google's encoded polyline format: a whole road path packed into one short string.
 * https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class Polyline
{
    /** @return list<array{0: float, 1: float}> [latitude, longitude] pairs */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = $lat = $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            foreach (['lat', 'lng'] as $axis) {
                $result = $shift = 0;

                do {
                    $byte = ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20 && $index < $length);

                $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);
                $axis === 'lat' ? $lat += $delta : $lng += $delta;
            }

            $points[] = [round($lat / 1e5, 5), round($lng / 1e5, 5)];
        }

        return $points;
    }

    /** @param list<array{0: float, 1: float}> $points */
    public static function encode(array $points): string
    {
        $out = '';
        $prevLat = $prevLng = 0;

        foreach ($points as [$lat, $lng]) {
            foreach ([[(int) round($lat * 1e5), &$prevLat], [(int) round($lng * 1e5), &$prevLng]] as [$value, &$previous]) {
                $delta = $value - $previous;
                $previous = $value;
                $delta = $delta < 0 ? ~($delta << 1) : ($delta << 1);

                while ($delta >= 0x20) {
                    $out .= chr((0x20 | ($delta & 0x1F)) + 63);
                    $delta >>= 5;
                }

                $out .= chr($delta + 63);
            }
            unset($previous);
        }

        return $out;
    }

    /**
     * Keeps at most $max points, evenly spread, always with the first and the last.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    public static function thin(array $points, int $max): array
    {
        $count = count($points);

        if ($count <= $max) {
            return $points;
        }

        $step = ($count - 1) / ($max - 1);

        return array_map(fn (int $i) => $points[(int) round($i * $step)], range(0, $max - 1));
    }
}
