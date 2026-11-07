<?php

namespace Modules\Deliveries\Actions;

use App\Support\Contracts\SmsSender;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Deliveries\Events\DeliveryPickedUp;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;

/**
 * The driver has collected a business's parcels for this run. Each customer is texted that her parcel is on its
 * way, with a code to give the driver at the door.
 *
 * The code goes straight out by SMS, like a login code, so it is never written anywhere readable: only its hash
 * is kept. Rule-driven messages go through Messaging; this one must always go out.
 */
class ConfirmPickup
{
    public function __construct(private SmsSender $sms) {}

    /** @return int how many parcels were collected */
    public function handle(DeliveryRun $run, Business $business): int
    {
        if ($run->status !== RunStatus::Out) {
            throw new DomainException($run->status === RunStatus::Planned ? 'Start the run first.' : 'This run has finished.');
        }

        $codes = DB::transaction(function () use ($run, $business) {
            $parcels = Delivery::query()->where('delivery_run_id', $run->id)->where('business_id', $business->id)
                ->where('status', DeliveryStatus::Assigned)->lockForUpdate()->get();

            if ($parcels->isEmpty()) {
                throw new DomainException("Nothing is waiting to be collected from {$business->name} on this run.");
            }

            return $parcels->map(function (Delivery $delivery) {
                $code = self::newCode();
                $delivery->update(['status' => DeliveryStatus::PickedUp, 'picked_up_at' => now(), 'code_hash' => Delivery::hashCode($code), 'code_attempts' => 0]);
                DeliveryPickedUp::dispatch($delivery);

                return [$delivery, $code];
            })->all();
        });

        foreach ($codes as [$delivery, $code]) {
            $this->sms->send($delivery->customer_phone, sprintf(
                '%s: your order is on its way with Aveo Ox, %s. Give the driver code %s at the door. Follow it: %s',
                $business->name, $delivery->zone->promiseFor($delivery->delivery_date), $code, $delivery->trackingUrl(),
            ));
        }

        return count($codes);
    }

    private static function newCode(): string
    {
        $digits = (int) config('deliveries.code_digits');

        return str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
    }
}
