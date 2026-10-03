<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Events\SubscriptionExpired;
use Modules\Subscriptions\Events\SubscriptionExpiring;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * The once-a-day housekeeping. Safe to run more than once in a day.
 *
 *  1. Tell riders whose subscription is about to end, once each.
 *  2. Expire subscriptions that have ended.
 *  3. Free seats nobody is paying for: lapsed past the grace period, or reserved and never paid.
 */
class RunDailyUpkeep
{
    public function __construct(private ReleaseSeat $releaseSeat) {}

    /** @return array{expiring: int, expired: int, released: int} */
    public function handle(?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $counts = ['expiring' => 0, 'expired' => 0, 'released' => 0];

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
                SubscriptionExpiring::dispatch($subscription);
                $counts['expiring']++;
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
