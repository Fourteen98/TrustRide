<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;

/** The business takes a parcel back before the driver collects it. A cancelled parcel doesn't count against its month. */
class CancelDelivery
{
    public function handle(Delivery $delivery): Delivery
    {
        if (! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned], true)) {
            throw new DomainException('Only a parcel not yet collected can be cancelled.');
        }

        $delivery->update(['status' => DeliveryStatus::Cancelled, 'cancelled_at' => now(), 'delivery_run_id' => null, 'position' => null]);

        return $delivery;
    }
}
