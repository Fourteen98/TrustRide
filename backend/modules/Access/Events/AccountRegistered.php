<?php

namespace Modules\Access\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;

class AccountRegistered extends BaseDomainEvent
{
    public function __construct(public int $accountId, public string $phone) {}

    public function name(): string
    {
        return 'account.registered';
    }

    public function payload(): array
    {
        return ['account_id' => $this->accountId, 'phone' => $this->phone];
    }

    public function recipients(): array
    {
        return [new Recipient(role: 'rider', phone: $this->phone)];
    }
}
