<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Models\Driver;

class SuspendDriver
{
    public function handle(Driver $driver, string $reason): Driver
    {
        if (trim($reason) === '') {
            throw new DomainException('Suspending a driver needs a reason.');
        }

        $driver->update(['status' => DriverStatus::Suspended, 'status_note' => trim($reason)]);

        return $driver;
    }
}
