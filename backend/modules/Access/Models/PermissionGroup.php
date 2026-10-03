<?php

namespace Modules\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A named bundle of permissions. For assigning and display only. Code checks permissions. */
class PermissionGroup extends Model
{
    protected $fillable = ['key', 'label', 'guard_name', 'module'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_group_permission');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission_group');
    }
}
