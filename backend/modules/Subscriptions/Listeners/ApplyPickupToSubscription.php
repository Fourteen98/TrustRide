<?php

namespace Modules\Subscriptions\Listeners;

use App\Support\Contracts\DomainEvent;
use Carbon\CarbonImmutable;
use Modules\Subscriptions\Actions\RecordMissedPickup;

/**
 * Hears pickups by their event name, so Subscriptions never has to know the Trips module.
 * Not queued: a "not here" and its correction must be applied in the order they happened.
 */
class ApplyPickupToSubscription
{
    public function __construct(private RecordMissedPickup $missed) {}

    public function handle(DomainEvent $event): void
    {
        if (! in_array($event->name(), ['rider.missed', 'rider.picked_up'], true)) {
            return;
        }

        $payload = $event->payload();

        if (! isset($payload['rider_id'], $payload['date'])) {
            return;
        }

        $date = CarbonImmutable::parse($payload['date']);

        $event->name() === 'rider.missed'
            ? $this->missed->handle((int) $payload['rider_id'], $date)
            : $this->missed->undo((int) $payload['rider_id'], $date);
    }
}
