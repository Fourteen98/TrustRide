<?php

namespace Tests\Fixtures;

use App\Support\BaseDomainEvent;

/** A made-up event, to show Messaging needs to know nothing about the module that fired it. */
class TestEvent extends BaseDomainEvent
{
    public function __construct(private string $eventName, private array $data, private array $people) {}

    public function name(): string
    {
        return $this->eventName;
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return $this->people;
    }
}
