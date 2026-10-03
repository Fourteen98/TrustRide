<?php

namespace Modules\Messaging\Providers;

use App\Support\Contracts\DomainEvent;
use App\Support\Contracts\ErasesPersonalData;
use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Modules\Messaging\Listeners\RecordDomainEvent;
use Modules\Messaging\Listeners\ScrubPersonalData;

class MessagingServiceProvider extends ModuleServiceProvider
{
    // One listener hears every business event, whichever module fired it.
    protected array $listen = [
        DomainEvent::class => [RecordDomainEvent::class],
        ErasesPersonalData::class => [ScrubPersonalData::class],
    ];

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
