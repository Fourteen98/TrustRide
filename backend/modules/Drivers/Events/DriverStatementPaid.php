<?php

namespace Modules\Drivers\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;

class DriverStatementPaid extends BaseDomainEvent
{
    /** @param  int  $amount  In pesewas */
    public function __construct(public int $driverId, public string $driverName, public string $phone, public string $month, public int $amount) {}

    public function name(): string
    {
        return 'driver.statement_paid';
    }

    public function payload(): array
    {
        return [
            'driver_id' => $this->driverId,
            'driver_name' => $this->driverName,
            'month' => $this->month,
            'amount' => number_format($this->amount / 100, 2),
        ];
    }

    public function recipients(): array
    {
        return [new Recipient(role: 'driver', phone: $this->phone, name: $this->driverName)];
    }
}
