<?php

namespace Modules\Subscriptions\Events;

use App\Support\BaseDomainEvent;
use App\Support\Money;
use App\Support\Recipient;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * Shared by the subscription events. The payload carries everything a receipt needs:
 * car number, colour, driver, stop and time.
 */
abstract class SubscriptionEvent extends BaseDomainEvent
{
    public int $subscriptionId;

    public int $riderId;

    /** @var array<string, mixed> */
    protected array $data;

    private Recipient $rider;

    public function __construct(Subscription $subscription)
    {
        $subscription->loadMissing(['rider.account', 'plan']);

        $seats = Seat::query()
            ->where('rider_id', $subscription->rider_id)
            ->whereIn('status', [SeatStatus::Active, SeatStatus::Reserved])
            ->with(['run.route.stops', 'run.driver', 'run.vehicle', 'stop'])
            ->get()
            ->map(fn (Seat $seat) => [
                'direction' => $seat->run->direction->value,
                'route' => $seat->run->route->name,
                'stop' => $seat->stop->name,
                'time' => $seat->pickupTime(),
                'driver_name' => $seat->run->driver->name,
                'car_number' => $seat->run->vehicle->registration_number,
                'car_colour' => $seat->run->vehicle->colour,
            ])
            ->values()
            ->all();

        $this->subscriptionId = $subscription->id;
        $this->riderId = $subscription->rider_id;
        $this->data = [
            'subscription_id' => $subscription->id,
            'rider_id' => $subscription->rider_id,
            'rider_name' => $subscription->rider->name,
            'plan' => $subscription->plan->name,
            'price' => Money::format($subscription->price),
            'starts_on' => $subscription->starts_on->toDateString(),
            'ends_on' => $subscription->ends_on->toDateString(),
            'seats' => $seats,
            // The first seat, flat, so a simple template can say {car_number} without a loop.
            ...($seats[0] ?? []),
        ];
        $this->rider = new Recipient(
            role: 'rider',
            phone: $subscription->rider->account->phone,
            channel: $subscription->rider->account->preferred_channel,
            name: $subscription->rider->name,
        );
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function recipients(): array
    {
        return [$this->rider];
    }
}
