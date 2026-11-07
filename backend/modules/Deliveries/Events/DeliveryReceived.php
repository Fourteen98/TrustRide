<?php

namespace Modules\Deliveries\Events;

/** A business has sent a parcel. Goes to the customer: when to expect it and the link to follow it. */
class DeliveryReceived extends DeliveryEvent
{
    public function name(): string
    {
        return 'delivery.received';
    }
}
