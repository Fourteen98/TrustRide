<?php

namespace Modules\Messaging\Providers;

use App\Support\Contracts\DomainEvent;
use App\Support\Contracts\ErasesPersonalData;
use App\Support\Contracts\SmsSender;
use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Messaging\Channels\ChannelManager;
use Modules\Messaging\Channels\ChannelSmsSender;
use Modules\Messaging\Console\PruneLogsCommand;
use Modules\Messaging\Listeners\RecordDomainEvent;
use Modules\Messaging\Listeners\ScrubPersonalData;

class MessagingServiceProvider extends ModuleServiceProvider
{
    // One listener hears every business event, whichever module fired it.
    protected array $listen = [
        DomainEvent::class => [RecordDomainEvent::class],
        ErasesPersonalData::class => [ScrubPersonalData::class],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ChannelManager::class);

        // Login codes and other must-send texts go out through the same SMS channel the rules use.
        $this->app->bind(SmsSender::class, ChannelSmsSender::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([PruneLogsCommand::class]);

        // Needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('messaging:prune')->dailyAt('03:20'));
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('messaging.sending', 'Messaging riders', Scope::Platform, [
                'messaging.log.view' => 'View the event and delivery log',
                'messaging.broadcast' => 'Message every rider on a run',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('messaging.rules', 'Message rules', Scope::Platform, [
                'messaging.rules.manage' => 'Change rules and templates',
            ]),
        ];
    }
}
