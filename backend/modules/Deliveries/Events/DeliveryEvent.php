<?php

namespace Modules\Deliveries\Events;

use App\Support\BaseDomainEvent;
use App\Support\Recipient;
use Modules\Deliveries\Models\Delivery;

/**
 * Shared by the parcel events. The payload holds what a message to the customer or the business might say.
 * The delivery code is never in it: that goes straight to the customer by SMS, like a login code.
 */
abstract class DeliveryEvent extends BaseDomainEvent
{
    public int $deliveryId;

    /** @var array<string, mixed> */
    protected array $data;

    /** @var list<Recipient> */
    protected array $people;

    public function __construct(Delivery $delivery, array $extra = [])
    {
        $delivery->loadMissing('business', 'zone');

        $this->deliveryId = $delivery->id;
        $this->data = [
            'delivery_id' => $delivery->id,
            'business_name' => $delivery->business->name,
            'customer_name' => $delivery->customer_name,
            'reference' => $delivery->reference ?? '',
            'zone' => $delivery->zone->name,
            'promise' => $delivery->zone->promiseFor($delivery->delivery_date),
            'track_url' => $delivery->trackingUrl(),
            'status' => $delivery->status->value,
            'failure_reason' => $delivery->failure_reason ?? '',
            ...$extra,
        ];
        $this->people = $this->recipientsFor($delivery);
    }

    /** @return list<Recipient> the customer, unless an event says otherwise */
    protected function recipientsFor(Delivery $delivery): array
    {
        return [new Recipient('customer', $delivery->customer_phone, 'sms', $delivery->customer_name)];
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return $this->people;
    }
}
