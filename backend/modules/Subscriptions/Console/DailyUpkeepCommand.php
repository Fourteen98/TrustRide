<?php

namespace Modules\Subscriptions\Console;

use Illuminate\Console\Command;
use Modules\Subscriptions\Actions\RunDailyUpkeep;

class DailyUpkeepCommand extends Command
{
    protected $signature = 'subscriptions:upkeep';

    protected $description = 'Warn riders whose subscription is ending, expire ended ones, and free unpaid seats. Runs daily.';

    public function handle(RunDailyUpkeep $upkeep): int
    {
        $counts = $upkeep->handle();

        $this->info("Told {$counts['expiring']} riders their subscription is ending. Expired {$counts['expired']}. Released {$counts['released']} seats.");

        return self::SUCCESS;
    }
}
