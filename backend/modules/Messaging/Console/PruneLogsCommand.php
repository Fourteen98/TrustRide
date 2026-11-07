<?php

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\Message;

class PruneLogsCommand extends Command
{
    protected $signature = 'messaging:prune';

    protected $description = 'Delete delivery log and event log entries older than the days set in config/messaging';

    public function handle(): int
    {
        $days = (int) config('messaging.log_retention_days');

        if ($days <= 0) {
            $this->info('Log retention is off.');

            return self::SUCCESS;
        }

        // Messages first. Those tied to an event would go with it anyway.
        $messages = Message::query()->where('created_at', '<', now()->subDays($days))->delete();
        $events = EventLog::query()->where('occurred_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$messages} messages and {$events} events older than {$days} days.");

        return self::SUCCESS;
    }
}
