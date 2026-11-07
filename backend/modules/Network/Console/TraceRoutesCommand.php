<?php

namespace Modules\Network\Console;

use App\Support\Maps\GoogleRoutes;
use Illuminate\Console\Command;
use Modules\Network\Actions\TraceRoutePath;
use Modules\Network\Models\Route;

class TraceRoutesCommand extends Command
{
    protected $signature = 'network:trace-routes {--all : Trace again even routes that already have a road}';

    protected $description = 'Ask Google for the road each route follows, so maps draw it along the streets';

    public function handle(TraceRoutePath $trace): int
    {
        Route::query()->where('is_active', true)
            ->when(! $this->option('all'), fn ($query) => $query->whereNull('path'))
            ->get()
            ->each(function (Route $route) use ($trace) {
                $result = $trace->handle($route);
                $this->line("{$route->name}: {$result}".($result === 'failed' && GoogleRoutes::lastProblem() ? ' ('.GoogleRoutes::lastProblem().')' : ''));
            });

        return self::SUCCESS;
    }
}
