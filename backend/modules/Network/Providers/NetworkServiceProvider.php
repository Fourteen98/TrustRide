<?php

namespace Modules\Network\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Network\Console\TraceRoutesCommand;
use Modules\Network\Jobs\TraceRoutePathJob;
use Modules\Network\Models\Stop;

class NetworkServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->commands([TraceRoutesCommand::class]);

        // A route Google could not trace yet, say because the key was new or the Routes API was off, is tried again
        // every hour, so maps follow the road as soon as it works. Traced routes are skipped, so this costs nothing
        // once every route has its road. Needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('network:trace-routes')->hourly()->withoutOverlapping());

        // A stop's pin moved, so every route through it follows a different road. Trace them again.
        Stop::updated(function (Stop $stop) {
            if ($stop->wasChanged(['latitude', 'longitude'])) {
                $stop->routes()->pluck('routes.id')->each(fn (int $id) => TraceRoutePathJob::dispatch($id)->afterCommit());
            }
        });
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('network.setup', 'Stops and routes', Scope::Platform, [
                'network.view' => 'View stops and routes',
                'network.manage' => 'Add and edit stops and routes',
            ], defaultRoles: ['operator']),
        ];
    }
}
