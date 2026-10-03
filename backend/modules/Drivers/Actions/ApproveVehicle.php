<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Drivers\Enums\VehicleStatus;
use Modules\Drivers\Models\Vehicle;

/** Approves a vehicle. Refuses until every required vetting item is passed or overridden. */
class ApproveVehicle
{
    public function __construct(private OutstandingVetting $outstanding) {}

    public function handle(Vehicle $vehicle): Vehicle
    {
        $missing = $this->outstanding->handle($vehicle);

        if ($missing->isNotEmpty()) {
            throw new DomainException('Vetting is not finished: '.$missing->pluck('label')->join('; ').'.');
        }

        $vehicle->update(['status' => VehicleStatus::Approved]);

        return $vehicle;
    }
}
