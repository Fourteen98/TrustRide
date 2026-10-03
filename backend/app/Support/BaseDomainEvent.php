<?php

namespace App\Support;

use App\Support\Contracts\DomainEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Extend this for every business event. It waits for the database transaction
 * to commit before it fires, so nobody is told about something that rolled back.
 */
abstract class BaseDomainEvent implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;

    private ?string $eventId = null;

    public function id(): string
    {
        return $this->eventId ??= (string) Str::uuid();
    }

    public function recipients(): array
    {
        return [];
    }
}
