<?php

namespace Modules\Deliveries\Queries;

use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;

/**
 * What a driver needs for one run: first the businesses to collect from, then each drop in order.
 * Customer phone numbers are included, so he can call from the door.
 */
class RunSheet
{
    /**
     * @return array{run_id: int, zone: string, date: string, status: string, pickups: list<array{business_id: int, name: string, address: string, landmark: ?string, phone: string, parcels: int, collected: bool}>, drops: list<array{delivery_id: int, position: int, business: string, reference: ?string, customer_name: string, customer_phone: string, address: string, landmark: ?string, notes: ?string, status: string, failure_reason: ?string}>, counts: array{total: int, delivered: int, failed: int, to_collect: int, with_driver: int}}
     */
    public function handle(DeliveryRun $run): array
    {
        $run->loadMissing('zone', 'deliveries.business');
        $parcels = $run->deliveries;

        return [
            'run_id' => $run->id,
            'zone' => $run->zone->name,
            'date' => $run->date->toDateString(),
            'status' => $run->status->value,
            'pickups' => $parcels->groupBy('business_id')->map(fn ($group) => [
                'business_id' => $group->first()->business->id,
                'name' => $group->first()->business->name,
                'address' => $group->first()->business->pickup_address,
                'landmark' => $group->first()->business->pickup_landmark,
                'phone' => $group->first()->business->phone,
                'parcels' => $group->count(),
                'collected' => $group->every(fn (Delivery $delivery) => $delivery->status !== DeliveryStatus::Assigned),
            ])->values()->all(),
            'drops' => $parcels->map(fn (Delivery $delivery) => [
                'delivery_id' => $delivery->id,
                'position' => (int) $delivery->position,
                'business' => $delivery->business->name,
                'reference' => $delivery->reference,
                'customer_name' => $delivery->customer_name,
                'customer_phone' => $delivery->customer_phone,
                'address' => $delivery->address,
                'landmark' => $delivery->landmark,
                'notes' => $delivery->notes,
                'status' => $delivery->status->value,
                'failure_reason' => $delivery->failure_reason,
            ])->values()->all(),
            'counts' => [
                'total' => $parcels->count(),
                'delivered' => $parcels->where('status', DeliveryStatus::Delivered)->count(),
                'failed' => $parcels->where('status', DeliveryStatus::Failed)->count(),
                'to_collect' => $parcels->where('status', DeliveryStatus::Assigned)->count(),
                'with_driver' => $parcels->where('status', DeliveryStatus::PickedUp)->count(),
            ],
        ];
    }
}
