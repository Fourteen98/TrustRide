<?php

namespace Modules\Messaging\Listeners;

use App\Support\Contracts\DomainEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Messaging\Actions\ApplyRules;
use Modules\Messaging\Models\EventLog;

/**
 * Hears every business event, whichever module fired it. Writes it to the log,
 * then applies the rules an admin has set for that event. Runs on the queue and is safe to run twice.
 */
class RecordDomainEvent implements ShouldQueue
{
    public function __construct(private ApplyRules $applyRules) {}

    public function handle(DomainEvent $event): void
    {
        $logged = EventLog::query()->firstOrCreate(
            ['event_id' => $event->id()],
            [
                'name' => $event->name(),
                'payload' => $event->payload(),
                'recipients' => array_map(fn ($recipient) => $recipient->toArray(), $event->recipients()),
                'occurred_at' => now(),
            ],
        );

        $this->applyRules->handle($logged);
    }
}
