<?php

namespace Modules\Deliveries\Events;

use App\Support\Recipient;
use Modules\Deliveries\Models\Delivery;

/** The customer has her parcel. Goes to the business, whose rule is off until a business asks for it. */
class DeliveryDelivered extends DeliveryEvent
{
    public function name(): string
    {
        return 'delivery.delivered';
    }

    protected function recipientsFor(Delivery $delivery): array
    {
        return [new Recipient('business', $delivery->business->phone, 'sms', $delivery->business->contact_name)];
    }
}
