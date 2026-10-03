<?php

namespace Modules\Network\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class NetworkServiceProvider extends ModuleServiceProvider
{
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
