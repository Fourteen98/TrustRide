<?php

namespace Modules\Support\Providers;

use App\Support\Contracts\ErasesPersonalData;
use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Support\Console\PruneCasesCommand;
use Modules\Support\Listeners\ScrubCases;

class SupportServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ErasesPersonalData::class => [ScrubCases::class],
    ];

    public function boot(): void
    {
        parent::boot();

        $this->commands([PruneCasesCommand::class]);

        // Needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('support:prune')->dailyAt('03:30'));
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('support.queue', 'Support queue', Scope::Platform, [
                'support.queue.view' => 'View the support queue',
                'support.queue.reply' => 'Reply to, assign and resolve cases',
                'support.incidents.manage' => 'Mark a case as an incident',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('support.self', 'Asking for help', Scope::App, [
                'support.alert.raise' => 'Write to Aveo for help',
            ], defaultRoles: ['rider', 'driver', 'mate']),
        ];
    }
}
