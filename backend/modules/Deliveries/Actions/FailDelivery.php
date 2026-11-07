<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Events\DeliveryFailed;
use Modules\Deliveries\Models\Delivery;

/** The driver couldn't hand it over. The customer is told why. An operator can then move it to another day. */
class FailDelivery
{
    public function handle(Delivery $delivery, string $reason): Delivery
    {
        if ($delivery->status !== DeliveryStatus::PickedUp) {
            throw new DomainException('Only a parcel the driver has collected can be marked not delivered.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Say why it couldn\'t be delivered.');
        }

        $delivery->update(['status' => DeliveryStatus::Failed, 'failed_at' => now(), 'failure_reason' => trim($reason)]);
        DeliveryFailed::dispatch($delivery);

        return $delivery;
    }
}
