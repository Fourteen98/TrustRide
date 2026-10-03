<?php

namespace Modules\Drivers\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;

class DriverApproved extends BaseDomainEvent
{
    public function __construct(public int $driverId, public string $driverName, public string $phone) {}

    public function name(): string
    {
        return 'driver.approved';
    }

    public function payload(): array
    {
        return ['driver_id' => $this->driverId, 'driver_name' => $this->driverName];
    }

    public function recipients(): array
    {
        return [new Recipient(role: 'driver', phone: $this->phone, name: $this->driverName)];
    }
}
