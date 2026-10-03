<?php

namespace Modules\Access\Actions;

use App\Support\ModuleRegistry;
use App\Support\Scope;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;
use Modules\Access\Models\Staff;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stores what the modules declared: permissions, default groups and system roles.
 * Safe to run on every deploy. It adds what is missing and never removes an admin's changes.
 */
class SyncDeclaredAccess
{
    /** name => [scope, label, screens] */
    public const SYSTEM_ROLES = [
        Staff::SUPER_ADMIN => [Scope::Platform, 'Super admin', null],
        'operator' => [Scope::Platform, 'Operator', null],
        'rider' => [Scope::App, 'Rider', 'rider'],
        'driver' => [Scope::App, 'Driver', 'driver'],
        'mate' => [Scope::App, 'Mate', 'mate'],
    ];

    public function __construct(
        private ModuleRegistry $modules,
        private RebuildRolePermissions $rebuild,
        private PermissionRegistrar $registrar,
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $newRoles = [];

            foreach (self::SYSTEM_ROLES as $name => [$scope, $label, $screens]) {
                $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => $scope->value]);

                if (! $role->exists) {
                    $role->fill(['label' => $label, 'screens' => $screens, 'is_system' => true])->save();
                    $newRoles[$scope->value][$name] = $role;
                }
            }

            foreach ($this->modules->permissionGroups() as $module => $definitions) {
                foreach ($definitions as $definition) {
                    $scope = $definition->scope->value;

                    $group = PermissionGroup::query()->firstOrCreate(
                        ['key' => $definition->key, 'guard_name' => $scope],
                        ['label' => $definition->label, 'module' => $module],
                    );

                    foreach ($definition->permissions as $name => $label) {
                        $permission = Permission::query()->firstOrNew(['name' => $name, 'guard_name' => $scope]);

                        if (! $permission->exists) {
                            $permission->fill(['label' => $label, 'module' => $module])->save();
                            // A newly declared permission joins its declared group.
                            $group->permissions()->syncWithoutDetaching([$permission->id]);
                        }
                    }

                    // Default groups are only handed to roles created in this run.
                    foreach ($definition->defaultRoles as $roleName) {
                        if (isset($newRoles[$scope][$roleName])) {
                            $newRoles[$scope][$roleName]->groups()->syncWithoutDetaching([$group->id]);
                        }
                    }
                }
            }

            $this->registrar->forgetCachedPermissions();

            Role::query()->get()->each(fn (Role $role) => $this->rebuild->handle($role));
        });

        $this->registrar->forgetCachedPermissions();
    }
}
