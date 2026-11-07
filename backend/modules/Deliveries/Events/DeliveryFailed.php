<?php

namespace Modules\Deliveries\Events;

/** The driver could not deliver. Goes to the customer, with the reason. */
class DeliveryFailed extends DeliveryEvent
{
    public function name(): string
    {
        return 'delivery.failed';
    }
}
