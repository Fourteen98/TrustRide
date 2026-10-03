<?php

namespace Modules\Drivers\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class DriversServiceProvider extends ModuleServiceProvider
{
    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('drivers.management', 'Drivers and vehicles', Scope::Platform, [
                'drivers.view' => 'View drivers and vehicles',
                'drivers.manage' => 'Add and edit drivers and vehicles',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('drivers.vetting', 'Driver vetting', Scope::Platform, [
                'drivers.vet' => 'Record vetting checks',
                'drivers.vetting.override' => 'Override a failed vetting check',
                'drivers.assess' => 'Record the monthly assessment',
            ]),
            new PermissionGroupDefinition('drivers.payments', 'Driver payments', Scope::Platform, [
                'drivers.statements.pay' => 'Mark a driver\'s month as paid',
            ]),
            new PermissionGroupDefinition('drivers.self', 'Own driver record', Scope::App, [
                'drivers.commission.view' => 'See own commission and assessment',
            ], defaultRoles: ['driver']),
        ];
    }
}
