<?php

namespace Modules\Support\Console;

use Illuminate\Console\Command;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Models\SupportCase;

class PruneCasesCommand extends Command
{
    protected $signature = 'support:prune';

    protected $description = 'Delete resolved cases older than the days set in config/support. Incidents are kept.';

    public function handle(): int
    {
        $days = (int) config('support.case_retention_days');

        if ($days <= 0) {
            $this->info('Case retention is off.');

            return self::SUCCESS;
        }

        $deleted = SupportCase::query()
            ->where('status', CaseStatus::Resolved)
            ->where('is_incident', false)
            ->where('resolved_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} resolved cases older than {$days} days.");

        return self::SUCCESS;
    }
}
