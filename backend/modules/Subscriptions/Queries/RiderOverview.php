<?php

namespace Modules\Subscriptions\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/** Everything Subscriptions knows about one rider that her home screen needs. */
class RiderOverview
{
    /**
     * @return array{seats: Collection<int, Seat>, subscription: ?Subscription, renewal: ?Subscription, notices: Collection<int, AbsenceNotice>, ride_dates: list<string>}
     */
    public function handle(Rider $rider): array
    {
        $today = CarbonImmutable::today();

        $seats = Seat::query()
            ->where('rider_id', $rider->id)
            ->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])
            ->with(['run.route.stops', 'run.driver', 'run.vehicle', 'stop'])
            ->get()
            ->sortBy(fn (Seat $seat) => $seat->run->departureTime())
            ->values();

        $upcoming = Subscription::query()
            ->where('rider_id', $rider->id)
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_on', '>=', $today->toDateString())
            ->with('plan')
            ->orderBy('starts_on')
            ->get();

        $notices = AbsenceNotice::query()
            ->where('rider_id', $rider->id)
            ->where('date', '>=', $today->toDateString())
            ->orderBy('date')
            ->get();

        return [
            'seats' => $seats,
            'subscription' => $upcoming->first(),
            'renewal' => $upcoming->skip(1)->first(),
            'notices' => $notices,
            'ride_dates' => $this->rideDates($seats, $upcoming, $notices, $today),
        ];
    }

    /**
     * The next days she could say she won't ride: days her van runs, inside a subscription,
     * with no notice given yet. Two weeks ahead at most.
     *
     * @return list<string>
     */
    private function rideDates(Collection $seats, Collection $subscriptions, Collection $notices, CarbonImmutable $today): array
    {
        $active = $seats->where('status', SeatStatus::Active);
        $taken = $notices->map(fn (AbsenceNotice $notice) => $notice->date->toDateString());

        return collect(range(0, 13))
            ->map(fn (int $offset) => $today->addDays($offset))
            ->filter(fn (CarbonImmutable $day) => $active->contains(fn (Seat $seat) => $seat->run->runsOn($day)))
            ->filter(fn (CarbonImmutable $day) => $subscriptions->contains(fn (Subscription $subscription) => $subscription->covers($day)))
            ->map(fn (CarbonImmutable $day) => $day->toDateString())
            ->reject(fn (string $day) => $taken->contains($day))
            ->values()
            ->all();
    }
}
