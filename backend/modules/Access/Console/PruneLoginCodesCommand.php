<?php

namespace Modules\Access\Console;

use Illuminate\Console\Command;
use Modules\Access\Models\LoginCode;

class PruneLoginCodesCommand extends Command
{
    protected $signature = 'access:prune';

    protected $description = 'Delete login codes more than a day old. They are useless after ten minutes.';

    public function handle(): int
    {
        $deleted = LoginCode::query()->where('created_at', '<', now()->subDay())->delete();

        $this->info("Deleted {$deleted} old login codes.");

        return self::SUCCESS;
    }
}
