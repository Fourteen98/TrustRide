<?php

namespace Modules\Subscriptions\Console;

use Illuminate\Console\Command;
use Modules\Riders\Actions\EraseRider;
use Modules\Subscriptions\Queries\RidersPastRetention;

class RetentionCommand extends Command
{
    protected $signature = 'subscriptions:retention {--dry-run : List who would be deleted, and delete nobody}';

    protected $description = 'Delete the data of riders who stopped riding longer ago than the retention period';

    public function handle(RidersPastRetention $query, EraseRider $erase): int
    {
        $days = (int) config('subscriptions.rider_retention_days');

        if ($days <= 0) {
            $this->info('No retention period is set, so nobody is deleted. Set one under Subscriptions > Plans > Renewal and seat rules.');

            return self::SUCCESS;
        }

        $riders = $query->handle($days);

        foreach ($riders as $row) {
            $this->line(($this->option('dry-run') ? 'Would delete ' : 'Deleting ')."rider {$row['rider']->id}, last active {$row['last_active']->toDateString()}.");

            if (! $this->option('dry-run')) {
                $erase->forRetention($row['rider']);
            }
        }

        $this->info($this->option('dry-run')
            ? "{$riders->count()} riders are past the {$days}-day retention period. Nobody was deleted."
            : "Deleted {$riders->count()} riders past the {$days}-day retention period.");

        return self::SUCCESS;
    }
}
