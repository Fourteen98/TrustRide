<?php

namespace Modules\Riders\Events;

use App\Support\BaseDomainEvent;
use App\Support\Contracts\ErasesPersonalData;

/**
 * A rider's data has been deleted. The event carries her phone number so other modules can remove
 * their copies, but it keeps the number out of its payload, so the event log never stores it.
 */
class RiderErased extends BaseDomainEvent implements ErasesPersonalData
{
    public function __construct(private string $phone, public int $requestId) {}

    public function phone(): string
    {
        return $this->phone;
    }

    public function name(): string
    {
        return 'rider.erased';
    }

    public function payload(): array
    {
        return ['deletion_request_id' => $this->requestId];
    }
}
