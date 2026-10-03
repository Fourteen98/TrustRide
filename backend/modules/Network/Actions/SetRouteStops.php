<?php

namespace Modules\Network\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Network\Models\Route;

/** Replaces a route's stops. The order given is the travelling order. */
class SetRouteStops
{
    /** @param  list<array{stop_id: int|string, minutes_from_start?: int|string|null}>  $stops */
    public function handle(Route $route, array $stops): Route
    {
        $stops = array_values($stops);
        $ids = array_map(fn (array $stop) => (int) $stop['stop_id'], $stops);

        if (count($ids) !== count(array_unique($ids))) {
            throw new DomainException('A stop can only be on a route once.');
        }

        if (count($ids) < 2) {
            throw new DomainException('A route needs at least two stops: a pickup and the destination.');
        }

        return DB::transaction(function () use ($route, $stops) {
            $route->stops()->detach();

            foreach ($stops as $index => $stop) {
                $route->stops()->attach((int) $stop['stop_id'], [
                    'position' => $index + 1,
                    'minutes_from_start' => (int) ($stop['minutes_from_start'] ?? 0),
                ]);
            }

            return $route->load('stops');
        });
    }
}
