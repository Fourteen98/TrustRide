<?php

namespace App\Support;

use App\Support\Permissions\PermissionGroupDefinition;

/**
 * What each module has declared about itself. Modules write to this from their
 * service provider. Access reads it to store permissions. The admin panel reads
 * it to find each module's screens.
 */
final class ModuleRegistry
{
    /** @var array<string, list<PermissionGroupDefinition>> */
    private array $groups = [];

    /** @var array<string, string> module name => path */
    private array $modules = [];

    public function register(string $module, string $path, array $groups): void
    {
        $this->modules[$module] = $path;
        $this->groups[$module] = array_values($groups);
    }

    /** @return array<string, string> */
    public function modules(): array
    {
        return $this->modules;
    }

    /** @return array<string, list<PermissionGroupDefinition>> */
    public function permissionGroups(): array
    {
        return $this->groups;
    }
}
