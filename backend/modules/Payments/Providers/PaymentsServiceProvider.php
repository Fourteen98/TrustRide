<?php

namespace Modules\Payments\Providers;

use App\Support\Contracts\ErasesPersonalData;
use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Payments\Console\ReconcileCommand;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Listeners\ScrubPayerDetails;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ErasesPersonalData::class => [ScrubPayerDetails::class],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(GatewayManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([ReconcileCommand::class]);

        // Needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
        });
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('payments.records', 'Payments', Scope::Platform, [
                'payments.view' => 'View payments and check their status with the gateway',
            ], defaultRoles: ['operator']),
            // Settling by hand gives a rider what she is paying for without the gateway saying so.
            new PermissionGroupDefinition('payments.settling', 'Settling payments by hand', Scope::Platform, [
                'payments.resolve' => 'Mark a payment paid or failed by hand',
            ]),
        ];
    }
}
