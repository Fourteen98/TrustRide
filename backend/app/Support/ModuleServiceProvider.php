<?php

namespace App\Support;

use App\Support\Permissions\PermissionGroupDefinition;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base provider for every module in /modules.
 *
 * A module owns its tables, migrations, models, actions and events.
 * It loads its own migrations and settings, declares its permissions, and says which
 * events it listens to. It does not hold routes or screens for a side,
 * except admin screens in Filament/, which the admin panel discovers.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> event => listeners */
    protected array $listen = [];

    public function register(): void
    {
        // A module's settings live in its own config.php, read as config('<module>.key').
        if (is_file($config = $this->modulePath('config.php'))) {
            $this->mergeConfigFrom($config, strtolower($this->moduleName()));
        }

        $this->app->make(ModuleRegistry::class)->register(
            $this->moduleName(),
            $this->modulePath(),
            $this->permissionGroups(),
        );
    }

    public function boot(): void
    {
        $migrations = $this->modulePath('Database/Migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        // Blade views for the module's admin pages, used as view('<module>::name').
        if (is_dir($views = $this->modulePath('Resources/views'))) {
            $this->loadViewsFrom($views, strtolower($this->moduleName()));
        }

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /** @return list<PermissionGroupDefinition> */
    protected function permissionGroups(): array
    {
        return [];
    }

    protected function moduleName(): string
    {
        return basename($this->modulePath());
    }

    protected function modulePath(string $path = ''): string
    {
        $root = dirname((new ReflectionClass(static::class))->getFileName(), 2);

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }
}
