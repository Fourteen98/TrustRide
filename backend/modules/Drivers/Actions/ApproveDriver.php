<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Events\DriverApproved;
use Modules\Drivers\Models\Driver;

/** Approves a driver. Refuses until every required vetting item is passed or overridden. */
class ApproveDriver
{
    public function __construct(private OutstandingVetting $outstanding) {}

    public function handle(Driver $driver, Staff $by): Driver
    {
        $missing = $this->outstanding->handle($driver);

        if ($missing->isNotEmpty()) {
            throw new DomainException('Vetting is not finished: '.$missing->pluck('label')->join('; ').'.');
        }

        $driver->update([
            'status' => DriverStatus::Approved,
            'status_note' => null,
            'approved_at' => now(),
            'approved_by' => $by->id,
        ]);

        DriverApproved::dispatch($driver->id, $driver->name, $driver->phone);

        return $driver;
    }
}
