<?php

namespace Modules\Messaging\Listeners;

use App\Support\Contracts\DomainEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Messaging\Models\EventLog;

/**
 * Writes every business event to the log. Runs on the queue and is safe to run twice.
 * Next step (task M2): look up the rules for this event name and queue the sends.
 */
class RecordDomainEvent implements ShouldQueue
{
    public function handle(DomainEvent $event): void
    {
        EventLog::query()->firstOrCreate(
            ['event_id' => $event->id()],
            [
                'name' => $event->name(),
                'payload' => $event->payload(),
                'recipients' => array_map(fn ($recipient) => $recipient->toArray(), $event->recipients()),
                'occurred_at' => now(),
            ],
        );
    }
}
