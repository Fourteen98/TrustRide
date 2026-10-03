<?php

namespace Modules\Trips\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class TripsServiceProvider extends ModuleServiceProvider
{
    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('trips.operations', 'Trips', Scope::Platform, [
                'trips.view' => 'View trips and the live map',
                'trips.status.set' => 'Set a trip status by hand',
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
