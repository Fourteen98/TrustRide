<?php

namespace App\Support;

/**
 * Someone an event concerns. Messaging only ever sees this, never a rider or driver model.
 */
final readonly class Recipient
{
    /**
     * @param  string  $role  e.g. rider, driver, mate, operator
     * @param  string  $phone  E.164, e.g. +233241234567
     * @param  string|null  $channel  Preferred channel: sms, whatsapp or push
     */
    public function __construct(
        public string $role,
        public string $phone,
        public ?string $channel = null,
        public ?string $name = null,
    ) {}

    public function toArray(): array
    {
        return ['role' => $this->role, 'phone' => $this->phone, 'channel' => $this->channel, 'name' => $this->name];
    }
}
