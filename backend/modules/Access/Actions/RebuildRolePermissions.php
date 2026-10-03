<?php

namespace Modules\Access\Actions;

use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;

/** Works out a role's effective permissions: everything in its groups plus its single ticks. */
class RebuildRolePermissions
{
    public function handle(Role $role): void
    {
        $fromGroups = Permission::query()
            ->whereHas('groups', fn ($groups) => $groups->whereIn('permission_groups.id', $role->groups()->pluck('permission_groups.id')))
            ->get();

        $role->syncPermissions($fromGroups->merge($role->tickedPermissions()->get())->unique('id'));
    }
}
