<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Events\DeliveryDelivered;
use Modules\Deliveries\Models\Delivery;

/**
 * The customer has her parcel. The driver types the code she gives him. After too many wrong codes he must call
 * her, or Aveo, and staff can confirm it without the code, saying why.
 */
class ConfirmDelivery
{
    public function handle(Delivery $delivery, ?string $code, ?Staff $staff = null, ?string $note = null): Delivery
    {
        if ($delivery->status !== DeliveryStatus::PickedUp) {
            throw new DomainException('Only a parcel the driver has collected can be delivered.');
        }

        if ($staff) {
            if (trim((string) $note) === '') {
                throw new DomainException('Say how you know she has it, since there is no code.');
            }

            $note = "Confirmed by {$staff->name} without the code: ".trim($note);
        } else {
            $this->checkCode($delivery, (string) $code);
        }

        $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now(), 'delivered_note' => $note]);
        DeliveryDelivered::dispatch($delivery);

        return $delivery;
    }

    private function checkCode(Delivery $delivery, string $code): void
    {
        $allowed = (int) config('deliveries.code_attempts');

        if ($delivery->code_attempts >= $allowed) {
            throw new DomainException('Too many wrong codes. Call the customer, or ask Aveo to confirm it.');
        }

        if ($delivery->code_hash && hash_equals($delivery->code_hash, Delivery::hashCode(trim($code)))) {
            return;
        }

        $delivery->increment('code_attempts');
        $left = $allowed - $delivery->code_attempts;

        throw new DomainException($left > 0
            ? "That code is wrong. {$left} ".($left === 1 ? 'try' : 'tries').' left.'
            : 'That code is wrong, and there are no tries left. Call the customer, or ask Aveo to confirm it.');
    }
}
