<?php

namespace Modules\Deliveries\Actions;

use Carbon\CarbonInterface;
use DomainException;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Events\DeliveryReceived;
use Modules\Deliveries\Models\Delivery;

/**
 * Puts a parcel back in the queue for another day: after "couldn't deliver", or to move one not yet collected.
 * The customer is told the new day.
 */
class RescheduleDelivery
{
    public function handle(Delivery $delivery, CarbonInterface $date): Delivery
    {
        if (! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned, DeliveryStatus::Failed], true)) {
            throw new DomainException('Only a parcel waiting, on a run not yet collected, or not delivered can be moved to another day.');
        }

        if ($date->copy()->startOfDay()->lt(today())) {
            throw new DomainException('Choose today or a later day.');
        }

        $delivery->update([
            'status' => DeliveryStatus::Pending,
            'delivery_date' => $date->toDateString(),
            'delivery_run_id' => null,
            'position' => null,
            'code_hash' => null,
            'code_attempts' => 0,
            'picked_up_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        DeliveryReceived::dispatch($delivery);

        return $delivery;
    }
}
