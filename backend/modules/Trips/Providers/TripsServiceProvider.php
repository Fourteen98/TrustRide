<?php

namespace Modules\Trips\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Trips\Console\EndStaleTrips;
use Modules\Trips\Console\PruneLocationHistory;
use Modules\Trips\Console\SimulateTrip;

class TripsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->commands([PruneLocationHistory::class, EndStaleTrips::class, SimulateTrip::class]);

        // Needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('trips:end-stale')->dailyAt('00:20');
            $schedule->command('trips:prune')->dailyAt('03:00');
        });
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('trips.operations', 'Trips', Scope::Platform, [
                'trips.view' => 'View trips and the live map',
                'trips.status.set' => 'Start and end a trip by hand',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('trips.riding', 'Riding', Scope::App, [
                'trips.follow' => 'Follow own van',
                'trips.share' => 'Share a trip link',
            ], defaultRoles: ['rider']),
            new PermissionGroupDefinition('trips.running', 'Running a trip', Scope::App, [
                'trips.run' => 'Start and end a trip',
                'trips.location.send' => 'Send the van location',
                'trips.riders.view' => 'See the riders on a run',
            ], defaultRoles: ['driver', 'mate']),
            new PermissionGroupDefinition('trips.pickups', 'Pickups', Scope::App, [
                'trips.pickups.mark' => 'Mark a rider picked up or missed',
            ], defaultRoles: ['mate']),
        ];
    }
}
