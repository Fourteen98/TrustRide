<?php

namespace Modules\Access\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Support\Facades\Gate;
use Modules\Access\Console\SyncAccessCommand;
use Modules\Access\Models\Staff;

class AccessServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->commands([SyncAccessCommand::class]);

        // A super admin passes every platform check. This never applies to mobile accounts.
        Gate::before(fn ($user) => $user instanceof Staff && $user->isSuperAdmin() ? true : null);
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('access.staff', 'Staff accounts', Scope::Platform, [
                'access.staff.view' => 'View staff',
                'access.staff.manage' => 'Add, edit and switch off staff',
            ]),
            new PermissionGroupDefinition('access.roles', 'Roles and permissions', Scope::Platform, [
                'access.roles.view' => 'View roles and groups',
                'access.roles.manage' => 'Create roles and change what they can do',
            ]),
            new PermissionGroupDefinition('access.accounts', 'Mobile accounts', Scope::Platform, [
                'access.accounts.view' => 'View mobile accounts',
                'access.accounts.manage' => 'Give mobile accounts roles and switch them off',
            ], defaultRoles: ['operator']),
        ];
    }
}
