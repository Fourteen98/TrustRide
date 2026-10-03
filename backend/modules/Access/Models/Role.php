<?php

namespace Modules\Access\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role holds groups and single ticked permissions. Its effective permissions
 * (what Spatie checks) are rebuilt from those by RebuildRolePermissions.
 */
class Role extends SpatieRole
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, 'role_permission_group');
    }

    public function tickedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_ticked_permission');
    }
}
