<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonInterface;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Subscription;

/**
 * Makes a subscription longer or shorter by whole days, for a day given back or taken back.
 * A renewal already bought starts the day after, so it moves with it.
 * Call it inside a transaction, with the subscription locked.
 */
class CreditDays
{
    public function handle(Subscription $subscription, int $days, CarbonInterface $because): void
    {
        $subscription->update([
            'ends_on' => $subscription->ends_on->addDays($days),
            'credited_days' => max(0, $subscription->credited_days + $days),
        ]);

        Subscription::query()
            ->where('rider_id', $subscription->rider_id)
            ->where('status', SubscriptionStatus::Active)
            ->where('starts_on', '>', $because->toDateString())
            ->whereKeyNot($subscription->id)
            ->get()
            ->each(fn (Subscription $later) => $later->update([
                'starts_on' => $later->starts_on->addDays($days),
                'ends_on' => $later->ends_on->addDays($days),
            ]));
    }
}
