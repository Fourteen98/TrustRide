<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Events\AutoRenewalUpcoming;
use Modules\Subscriptions\Events\SubscriptionExpired;
use Modules\Subscriptions\Events\SubscriptionExpiring;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * The once-a-day housekeeping. Safe to run more than once in a day.
 *
 *  1. Tell riders whose subscription is about to end, once each.
 *  1b. Take the payment for riders on automatic renewal whose subscription is ending.
 *  2. Expire subscriptions that have ended.
 *  3. Free seats nobody is paying for: lapsed past the grace period, or reserved and never paid.
 */
class RunDailyUpkeep
{
    public function __construct(private ReleaseSeat $releaseSeat, private ChargeAutoRenewal $charge) {}

    /** @return array{expiring: int, charged: int, expired: int, released: int} */
    public function handle(?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $counts = ['expiring' => 0, 'charged' => 0, 'expired' => 0, 'released' => 0];

        $hasLaterSubscription = fn (Subscription $subscription) => Subscription::query()
            ->where('rider_id', $subscription->rider_id)
            ->where('status', SubscriptionStatus::Active)
            ->where('starts_on', '>', $subscription->ends_on->toDateString())
            ->exists();

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNull('expiring_notified_at')
            ->whereBetween('ends_on', [$today->toDateString(), $today->addDays(config('subscriptions.expiring_notice_days'))->toDateString()])
            ->get()
            ->reject($hasLaterSubscription)
            ->each(function (Subscription $subscription) use (&$counts) {
                $subscription->update(['expiring_notified_at' => now()]);
                $renewal = AutoRenewal::query()->where('rider_id', $subscription->rider_id)->where('status', AutoRenewalStatus::Active)->first();

                // "Renew to keep your seat" would be wrong for someone who renews by herself. She is told what will be taken.
                $renewal
                    ? AutoRenewalUpcoming::dispatch($renewal, [
                        'ends_on_text' => $subscription->ends_on->format('j M Y'),
                        'charge_on_text' => $subscription->ends_on->subDays((int) config('subscriptions.auto_renew_charge_days_before'))->format('j M'),
                    ])
                    : SubscriptionExpiring::dispatch($subscription);
                $counts['expiring']++;
            });

        // Before anything is expired, so a renewal that goes through today keeps her subscription unbroken.
        AutoRenewal::query()
            ->where('status', AutoRenewalStatus::Active)
            ->get()
            ->each(function (AutoRenewal $renewal) use ($today, &$counts) {
                if ($this->charge->handle($renewal, $today) === 'charged') {
                    $counts['charged']++;
                }
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_on', '<', $today->toDateString())
            ->get()
            ->each(function (Subscription $subscription) use (&$counts) {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                SubscriptionExpired::dispatch($subscription);
                $counts['expired']++;
            });

        $graceEnds = $today->subDays(config('subscriptions.seat_grace_days'))->toDateString();
        $reservationEnds = $today->subDays(config('subscriptions.reservation_days'));

        Seat::query()
            ->whereIn('status', [SeatStatus::Active, SeatStatus::Reserved])
            ->get()
            ->each(function (Seat $seat) use ($graceEnds, $reservationEnds, &$counts) {
                $subscriptions = Subscription::query()->where('rider_id', $seat->rider_id);

                if ((clone $subscriptions)->where('status', SubscriptionStatus::Active)->exists()) {
                    return;
                }

                $lastEnded = (clone $subscriptions)->max('ends_on');

                $lapsed = $seat->status === SeatStatus::Active
                    && ($lastEnded === null || substr((string) $lastEnded, 0, 10) <= $graceEnds);

                $neverPaid = $seat->status === SeatStatus::Reserved && $seat->created_at->lte($reservationEnds);

                if ($lapsed || $neverPaid) {
                    $this->releaseSeat->handle($seat);
                    $counts['released']++;
                }
            });

        return $counts;
    }
}
