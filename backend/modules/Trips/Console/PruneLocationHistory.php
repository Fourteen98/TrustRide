<?php

namespace Modules\Trips\Console;

use Illuminate\Console\Command;
use Modules\Trips\Models\LocationPing;

class PruneLocationHistory extends Command
{
    protected $signature = 'trips:prune';

    protected $description = 'Delete location history older than the days set in config/trips';

    public function handle(): int
    {
        $days = (int) config('trips.location_history_days');
        $deleted = LocationPing::query()->where('recorded_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} positions older than {$days} days.");

        return self::SUCCESS;
    }
}
