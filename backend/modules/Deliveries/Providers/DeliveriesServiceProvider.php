<?php

namespace Modules\Deliveries\Providers;

use App\Support\ModuleServiceProvider;
use App\Support\Permissions\PermissionGroupDefinition;
use App\Support\Scope;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Deliveries\Console\PruneCustomersCommand;

/** Aveo Ox: parcel delivery for businesses, batched by zone. */
class DeliveriesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->commands([PruneCustomersCommand::class]);

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('ox:prune-customers')->dailyAt('03:40'));
    }

    protected function permissionGroups(): array
    {
        return [
            new PermissionGroupDefinition('deliveries.parcels', 'Ox parcels and runs', Scope::Platform, [
                'deliveries.view' => 'View Ox businesses, parcels and runs',
                'deliveries.parcels.manage' => 'Enter, upload, cancel and reschedule parcels',
                'deliveries.runs.manage' => 'Build runs, and confirm a parcel delivered without its code',
            ], defaultRoles: ['operator']),
            new PermissionGroupDefinition('deliveries.setup', 'Ox setup and billing', Scope::Platform, [
                'deliveries.setup' => 'Manage Ox zones, plans and businesses',
                'deliveries.invoices.manage' => 'Issue Ox invoices and mark them paid',
            ]),
            new PermissionGroupDefinition('deliveries.self', 'Delivering parcels', Scope::App, [
                'deliveries.runs.drive' => 'Drive Ox runs: collect and deliver parcels',
            ], defaultRoles: ['driver']),
        ];
    }
}
