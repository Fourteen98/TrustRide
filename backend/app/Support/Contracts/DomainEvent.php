<?php

namespace App\Support\Contracts;

use App\Support\Recipient;

/**
 * The one shape every business event follows.
 *
 * A module fires an event to say what happened. It does not know who listens.
 * Messaging catches every DomainEvent, logs it, and applies the rules an admin has set.
 */
interface DomainEvent
{
    /** Unique id for this occurrence. Listeners use it to stay safe when run twice. */
    public function id(): string;

    /** e.g. subscription.activated */
    public function name(): string;

    /** The data a message template can use. Plain values only. */
    public function payload(): array;

    /** @return list<Recipient> */
    public function recipients(): array;
}
