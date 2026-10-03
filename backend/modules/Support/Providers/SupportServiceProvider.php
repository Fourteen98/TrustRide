<?php

namespace Modules\Support\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class SupportServiceProvider extends ModuleServiceProvider
{
    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('support.queue', 'Support queue', Scope::Platform, [
                'support.queue.view' => 'View the support queue',
                'support.queue.reply' => 'Reply to and assign conversations',
                'support.incidents.manage' => 'Record and close incidents',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('support.self', 'Asking for help', Scope::App, [
                'support.alert.raise' => 'Raise an alert to an operator',
            ], defaultRoles: ['rider', 'driver', 'mate']),
        ];
    }
}
