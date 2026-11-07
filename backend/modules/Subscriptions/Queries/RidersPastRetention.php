<?php

namespace Modules\Subscriptions\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\PlanPurchase;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * Riders Aveo has no more reason to keep data about.
 *
 * Someone is only on this list if she has nothing live with us: no seat, no running or coming
 * subscription, no application waiting, no payment under way, no direct debit. And the last thing she
 * did have ended longer ago than the retention period. Anything live keeps her off it, however old.
 */
class RidersPastRetention
{
    /** @return Collection<int, array{rider: Rider, last_active: CarbonImmutable}> */
    public function handle(int $days, ?CarbonImmutable $today = null): Collection
    {
        if ($days <= 0) {
            return collect();
        }

        $cutoff = ($today ?? CarbonImmutable::today())->subDays($days);

        return Rider::query()
            ->whereNotIn('id', Seat::query()->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])->select('rider_id'))
            ->whereNotIn('id', Subscription::query()->where('status', SubscriptionStatus::Active)->select('rider_id'))
            ->whereNotIn('id', RiderApplication::query()->where('status', ApplicationStatus::Pending)->select('rider_id'))
            ->whereNotIn('id', PlanPurchase::query()->whereIn('status', ['pending', 'needs_attention'])->select('rider_id'))
            ->whereNotIn('id', AutoRenewal::query()->where('status', '!=', 'off')->select('rider_id'))
            ->get()
            ->map(fn (Rider $rider) => ['rider' => $rider, 'last_active' => $this->lastActive($rider)])
            ->filter(fn (array $row) => $row['last_active']->lt($cutoff))
            ->values();
    }

    /** The latest day she had anything with us. */
    private function lastActive(Rider $rider): CarbonImmutable
    {
        return collect([
            $rider->created_at,
            Subscription::query()->where('rider_id', $rider->id)->max('ends_on'),
            RiderApplication::query()->where('rider_id', $rider->id)->max('updated_at'),
            Seat::query()->where('rider_id', $rider->id)->max('updated_at'),
        ])->filter()->map(fn ($moment) => CarbonImmutable::parse($moment))->max();
    }
}
