<?php

namespace App\Web;

use Illuminate\Contracts\View\View;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;

/**
 * An Ox customer's page: /o/{public id}, the link in her SMS. No login and no app.
 *
 * It says where her parcel is and when to expect it. It shows her first name only, and never her phone,
 * her address or the code. The link can't be guessed.
 */
class ParcelPageController
{
    public function show(string $publicId): View
    {
        $delivery = Delivery::query()->where('public_id', $publicId)->with('business', 'zone', 'run.driver')->firstOrFail();
        $status = $delivery->status;

        $steps = [
            ['label' => "{$delivery->business->name} sent it", 'done' => true],
            ['label' => 'Collected by the driver', 'done' => in_array($status, [DeliveryStatus::PickedUp, DeliveryStatus::Delivered, DeliveryStatus::Failed], true)],
            ['label' => 'Delivered', 'done' => $status === DeliveryStatus::Delivered],
        ];

        return view('parcel', [
            'delivery' => $delivery,
            'firstName' => strtok($delivery->customer_name, ' ') ?: 'there',
            'headline' => match ($status) {
                DeliveryStatus::Pending, DeliveryStatus::Assigned => 'Expected '.$delivery->zone->promiseFor($delivery->delivery_date),
                DeliveryStatus::PickedUp => 'On its way, '.$delivery->zone->promiseFor($delivery->delivery_date),
                DeliveryStatus::Delivered => 'Delivered '.$delivery->delivered_at->format('D j M, g:ia'),
                DeliveryStatus::Failed => 'We couldn\'t deliver it',
                DeliveryStatus::Cancelled => 'This order was cancelled',
            },
            'detail' => match ($status) {
                DeliveryStatus::PickedUp => 'Have your code ready. It is in the text message we sent you. Give it to the driver only when you have the parcel.',
                DeliveryStatus::Failed => trim(($delivery->failure_reason ? "{$delivery->failure_reason}. " : '').'Aveo will call you to arrange another day.'),
                DeliveryStatus::Cancelled => "Ask {$delivery->business->name} if you weren't expecting this.",
                default => null,
            },
            'driver' => $status === DeliveryStatus::PickedUp ? strtok((string) $delivery->run?->driver?->name, ' ') : null,
            'steps' => $status === DeliveryStatus::Cancelled ? [] : $steps,
        ]);
    }
}
