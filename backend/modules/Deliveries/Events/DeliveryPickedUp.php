<?php

namespace Modules\Deliveries\Events;

/** The driver has collected the parcel. The customer's code went to her by SMS directly, so this only records it. */
class DeliveryPickedUp extends DeliveryEvent
{
    public function name(): string
    {
        return 'delivery.picked_up';
    }

    public function recipients(): array
    {
        return [];
    }
}
