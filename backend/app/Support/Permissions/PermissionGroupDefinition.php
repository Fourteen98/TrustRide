<?php

namespace App\Support\Permissions;

use App\Support\Scope;

/**
 * A module's declaration of one default permission group.
 *
 * Every permission a module declares sits in a group. Admins can regroup later
 * in the console. Code only ever checks permissions, never groups.
 */
final readonly class PermissionGroupDefinition
{
    /**
     * @param  string  $key  Unique per scope, e.g. "drivers.vetting"
     * @param  array<string, string>  $permissions  name => label, e.g. ['drivers.vet' => 'Vet a driver']
     * @param  list<string>  $defaultRoles  Role names that get this group when the role is first created
     */
    public function __construct(
        public string $key,
        public string $label,
        public Scope $scope,
        public array $permissions,
        public array $defaultRoles = [],
    ) {}
}
