<?php

namespace App\Support\Maps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google's Routes API: the road path between stops, and how long the drive takes in today's traffic.
 *
 *   POST https://routes.googleapis.com/directions/v2:computeRoutes
 *
 * Every call is billed by Google, so this is careful with them. Nothing is called without a key.
 * A daily ceiling stops a bug, or a busy day, from running up a bill: past it, callers get null
 * and the app falls back to "stops away". Any failure also returns null, never an exception.
 */
final class GoogleRoutes
{
    public const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    /** Why the last call gave nothing, in Google's words when it said. For the console and the trace command. */
    private static ?string $problem = null;

    public static function lastProblem(): ?string
    {
        return self::$problem;
    }

    public static function configured(): bool
    {
        return (bool) config('aveo.maps.google_key');
    }

    /**
     * The road path through the points in order. For drawing a route once, so traffic is ignored.
     *
     * @param  list<array{0: float, 1: float}>  $points  at least two, at most 27
     * @return list<array{0: float, 1: float}>|null
     */
    public static function path(array $points): ?array
    {
        $route = self::call($points, trafficAware: false, fields: 'routes.polyline.encodedPolyline');
        $encoded = $route['polyline']['encodedPolyline'] ?? null;

        return $encoded ? Polyline::decode($encoded) : null;
    }

    /**
     * Seconds to drive through the points in order, in traffic now.
     *
     * @param  list<array{0: float, 1: float}>  $points
     */
    public static function seconds(array $points): ?int
    {
        $route = self::call($points, trafficAware: true, fields: 'routes.duration');
        $duration = $route['duration'] ?? null; // e.g. "754s"

        return is_string($duration) ? (int) rtrim($duration, 's') : null;
    }

    private static function call(array $points, bool $trafficAware, string $fields): ?array
    {
        self::$problem = match (true) {
            ! self::configured() => 'GOOGLE_MAPS_API_KEY is not set.',
            count($points) < 2 || count($points) > 27 => 'A route needs between 2 and 27 pinned stops.',
            ! self::withinDailyLimit() => 'Today\'s limit of Google calls (GOOGLE_MAPS_DAILY_LIMIT) is used up.',
            default => null,
        };

        if (self::$problem !== null) {
            return null;
        }

        $at = fn (array $point) => ['location' => ['latLng' => ['latitude' => $point[0], 'longitude' => $point[1]]]];

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => config('aveo.maps.google_key'),
                'X-Goog-FieldMask' => $fields,
            ])->acceptJson()->asJson()->timeout(10)->post(self::ENDPOINT, array_filter([
                'origin' => $at($points[0]),
                'destination' => $at($points[count($points) - 1]),
                'intermediates' => array_map($at, array_slice($points, 1, -1)) ?: null,
                'travelMode' => 'DRIVE',
                'routingPreference' => $trafficAware ? 'TRAFFIC_AWARE' : 'TRAFFIC_UNAWARE',
            ]));
        } catch (Throwable $e) {
            self::$problem = 'Google could not be reached: '.$e->getMessage();
            Log::warning('Google Routes could not be reached', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            // e.g. "Routes API has not been used in project ... or it is disabled. Enable it by visiting ..."
            self::$problem = 'Google said: '.($response->json('error.message') ?? 'HTTP '.$response->status());
            Log::warning('Google Routes refused a request', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            return null;
        }

        $route = $response->json('routes.0');
        self::$problem = $route === null ? 'Google found no road between these stops.' : null;

        return $route;
    }

    /** Counts today's calls. Past the limit, no more are made until tomorrow. */
    private static function withinDailyLimit(): bool
    {
        $key = 'maps:google:calls:'.now()->toDateString();
        Cache::add($key, 0, now()->endOfDay());

        return Cache::increment($key) <= (int) config('aveo.maps.daily_limit');
    }
}
