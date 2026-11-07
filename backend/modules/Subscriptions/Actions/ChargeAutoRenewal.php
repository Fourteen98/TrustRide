<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\Subscription;

/**
 * Takes the plan's price through her mandate when it is due, and at most once a day.
 *
 * Due means: she has no subscription that reaches past the charge day. So it runs straight after she
 * approves if she has nothing, and a day before the end if she has something. The charge is an ordinary
 * plan purchase, so the same code activates it and the same receipt goes out.
 *
 * Three failed charges in a row turn automatic renewal off and she is told.
 */
class ChargeAutoRenewal
{
    public function __construct(private StartPlanPurchase $purchase, private StopAutoRenewal $stop) {}

    /** @return string charged, not_due, tried_today, failed, stopped or inactive */
    public function handle(AutoRenewal $renewal, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $renewal->loadMissing(['rider', 'plan', 'mandate']);

        if (! $renewal->isActive() || ! $renewal->mandate?->isActive()) {
            return 'inactive';
        }

        $coveredUntil = Subscription::query()
            ->where('rider_id', $renewal->rider_id)->where('status', SubscriptionStatus::Active)->max('ends_on');
        $chargeFrom = $today->addDays((int) config('subscriptions.auto_renew_charge_days_before'));

        if ($coveredUntil !== null && CarbonImmutable::parse($coveredUntil)->gt($chargeFrom)) {
            return 'not_due';
        }

        if ($renewal->last_attempt_on?->equalTo($today)) {
            return 'tried_today';
        }

        $renewal->update(['last_attempt_on' => $today]);

        try {
            $purchase = $this->purchase->handle($renewal->rider, $renewal->plan, (string) $renewal->mandate->phone, $renewal->mandate->network, $renewal->mandate, $renewal->seats);
            $purchase->update(['auto_renewal_id' => $renewal->id]);

            return 'charged';
        } catch (DomainException $e) {
            return $this->failed($renewal, $e->getMessage());
        }
    }

    /** Counts a charge that did not go through. Called here, and when a charge that started later fails. */
    public function failed(AutoRenewal $renewal, string $reason): string
    {
        $renewal->update(['failures' => $renewal->failures + 1, 'last_error' => str($reason)->limit(250)->toString()]);

        if ($renewal->failures >= (int) config('subscriptions.auto_renew_max_failures')) {
            $this->stop->handle($renewal, $renewal->last_error);

            return 'stopped';
        }

        return 'failed';
    }
}
