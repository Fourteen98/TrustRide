<?php

namespace Modules\Network\Actions;

use App\Support\Maps\GoogleRoutes;
use App\Support\Maps\Polyline;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;

/**
 * Asks Google for the road that joins a route's stops, in order, and keeps it on the route.
 * Done once per route and again only when its stops or their pins change, so it costs almost nothing.
 *
 * @return string traced, no_key, missing_pins or failed
 */
class TraceRoutePath
{
    public function handle(Route $route): string
    {
        $stops = $route->stops()->get();

        if (! GoogleRoutes::configured()) {
            return 'no_key';
        }

        if ($stops->contains(fn (Stop $stop) => $stop->latitude === null)) {
            $route->update(['path' => null, 'path_traced_at' => null]);

            return 'missing_pins';
        }

        $path = GoogleRoutes::path($stops->map(fn (Stop $stop) => [$stop->latitude, $stop->longitude])->all());

        if ($path === null) {
            return 'failed';
        }

        $route->update(['path' => Polyline::encode(Polyline::thin($path, 800)), 'path_traced_at' => now()]);

        return 'traced';
    }
}
