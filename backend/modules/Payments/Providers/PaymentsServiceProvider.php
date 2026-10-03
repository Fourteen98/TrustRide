<?php

namespace Modules\Payments\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('payments.records', 'Payments', Scope::Platform, [
                'payments.view' => 'View charges and receipts',
            ], defaultRoles: ['operator']),
        ];
    }
}
