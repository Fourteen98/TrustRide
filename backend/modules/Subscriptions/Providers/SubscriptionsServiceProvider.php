<?php

namespace Modules\Subscriptions\Providers;

use App\Support\Contracts\DomainEvent;
use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Payments\Events\MandateApproved;
use Modules\Payments\Events\MandateCancelled;
use Modules\Payments\Events\MandateFailed;
use Modules\Payments\Events\PaymentFailed;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Subscriptions\Console\DailyUpkeepCommand;
use Modules\Subscriptions\Listeners\ApplyPickupToSubscription;
use Modules\Subscriptions\Listeners\FollowMandate;
use Modules\Subscriptions\Listeners\SettlePurchaseWhenPaymentSettles;

class SubscriptionsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        PaymentSucceeded::class => [SettlePurchaseWhenPaymentSettles::class],
        PaymentFailed::class => [SettlePurchaseWhenPaymentSettles::class],
        DomainEvent::class => [ApplyPickupToSubscription::class],
        MandateApproved::class => [FollowMandate::class],
        MandateFailed::class => [FollowMandate::class],
        MandateCancelled::class => [FollowMandate::class],
    ];

    public function boot(): void
    {
        parent::boot();

        $this->commands([DailyUpkeepCommand::class]);

        // The module schedules its own daily job. It needs `php artisan schedule:work` (or cron) running.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('subscriptions:upkeep')->dailyAt('00:10');
        });
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('subscriptions.management', 'Subscriptions', Scope::Platform, [
                'subscriptions.view' => 'View subscriptions and seats',
                'subscriptions.activate' => 'Activate a subscription by hand',
                'subscriptions.runs.manage' => 'Add and change runs and the seats held on them',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('subscriptions.plans', 'Plans and pricing', Scope::Platform, [
                'subscriptions.plans.manage' => 'Change plans, prices and the no-show policy',
                'subscriptions.settings.manage' => 'Change the renewal and seat rules',
            ]),
            new PermissionGroupDefinition('subscriptions.self', 'Own subscription', Scope::App, [
                'subscriptions.buy' => 'Buy a subscription',
                'subscriptions.renew' => 'Renew a subscription',
                'subscriptions.absence.give' => 'Say she won\'t ride on a day',
            ], defaultRoles: ['rider']),
        ];
    }
}
