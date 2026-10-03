<?php

namespace Modules\Riders\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class RidersServiceProvider extends ModuleServiceProvider
{
    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('riders.management', 'Riders', Scope::Platform, [
                'riders.view' => 'View riders and applications',
                'riders.review' => 'Approve or reject an application',
            ], defaultRoles: ['operator']),
            // Not given to operators by default. Erasing a rider cannot be undone.
            new PermissionGroupDefinition('riders.privacy', 'Rider data deletion', Scope::Platform, [
                'riders.data.erase' => 'Delete a rider\'s data for good',
            ]),
            new PermissionGroupDefinition('riders.self', 'Own rider profile', Scope::App, [
                'riders.profile.manage' => 'Manage own profile and application',
                'riders.data.delete' => 'Ask for own data to be deleted',
            ], defaultRoles: ['rider']),
        ];
    }
}
