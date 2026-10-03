<?php

namespace Modules\Access\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Access\Exceptions\ScopeMismatch;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;

/** Sets which groups and which single permissions a role has. The only way to change a role's access. */
class SetRoleAccess
{
    public function __construct(private RebuildRolePermissions $rebuild) {}

    /**
     * @param  list<int>  $groupIds
     * @param  list<int>  $permissionIds
     */
    public function handle(Role $role, array $groupIds, array $permissionIds = []): Role
    {
        $scope = $role->guard_name;

        if (PermissionGroup::whereIn('id', $groupIds)->where('guard_name', '!=', $scope)->exists()) {
            throw ScopeMismatch::for('Groups given to this role', $scope);
        }

        if (Permission::whereIn('id', $permissionIds)->where('guard_name', '!=', $scope)->exists()) {
            throw ScopeMismatch::for('Permissions given to this role', $scope);
        }

        return DB::transaction(function () use ($role, $groupIds, $permissionIds) {
            $role->groups()->sync($groupIds);
            $role->tickedPermissions()->sync($permissionIds);
            $this->rebuild->handle($role);

            return $role;
        });
    }
}
