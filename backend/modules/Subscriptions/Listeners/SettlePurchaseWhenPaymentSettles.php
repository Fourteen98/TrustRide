<?php

namespace Modules\Subscriptions\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Payments\Events\PaymentEvent;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Subscriptions\Actions\SettlePlanPurchase;
use Modules\Subscriptions\Models\PlanPurchase;

/**
 * Hears every payment result and acts only on the ones that are for a plan purchase.
 * Queued, so a slow moment here never holds up Hubtel's callback, and a crash is retried.
 */
class SettlePurchaseWhenPaymentSettles implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private SettlePlanPurchase $settle) {}

    public function handle(PaymentEvent $event): void
    {
        if ($event->payableType !== PlanPurchase::PAYABLE) {
            return;
        }

        $purchase = PlanPurchase::query()->find($event->payableId);

        if (! $purchase) {
            return;
        }

        $event instanceof PaymentSucceeded ? $this->settle->paid($purchase) : $this->settle->failed($purchase);
    }
}
