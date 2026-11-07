<?php

namespace Modules\Network\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Network\Actions\TraceRoutePath;
use Modules\Network\Models\Route;

/** Traces a route's road in the background, after its stops or their pins change. */
class TraceRoutePathJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $routeId) {}

    public function handle(TraceRoutePath $trace): void
    {
        if ($route = Route::query()->find($this->routeId)) {
            $trace->handle($route);
        }
    }
}
