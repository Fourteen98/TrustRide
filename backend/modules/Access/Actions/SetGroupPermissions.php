<?php

namespace Modules\Access\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Access\Exceptions\ScopeMismatch;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;

/** Changes what a group holds, then updates every role that has the group. */
class SetGroupPermissions
{
    public function __construct(private RebuildRolePermissions $rebuild) {}

    /** @param  list<int>  $permissionIds */
    public function handle(PermissionGroup $group, array $permissionIds): PermissionGroup
    {
        if (Permission::whereIn('id', $permissionIds)->where('guard_name', '!=', $group->guard_name)->exists()) {
            throw ScopeMismatch::for('Permissions in this group', $group->guard_name);
        }

        return DB::transaction(function () use ($group, $permissionIds) {
            $group->permissions()->sync($permissionIds);
            $group->roles()->get()->each(fn ($role) => $this->rebuild->handle($role));

            return $group;
        });
    }
}
