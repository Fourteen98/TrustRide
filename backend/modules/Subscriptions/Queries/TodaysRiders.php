<?php

namespace Modules\Subscriptions\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * Who a run carries on a given day, in the order the van reaches them.
 * A rider is on the list if her seat is active and her subscription covers the day.
 */
class TodaysRiders
{
    /**
     * @return Collection<int, array{rider_id: int, name: string, phone: string, stop: string, time: ?string, absent: bool}>
     */
    public function handle(Run $run, ?CarbonInterface $date = null): Collection
    {
        $date = CarbonImmutable::parse($date ?? now())->startOfDay();

        if (! $run->is_active || ! $run->runsOn($date)) {
            return collect();
        }

        $run->loadMissing('route.stops');
        $order = $run->route->stops->pluck('pivot.position', 'id');

        $seats = Seat::query()
            ->where('run_id', $run->id)
            ->where('status', SeatStatus::Active)
            ->whereIn('rider_id', Subscription::query()->covering($date)->select('rider_id'))
            ->with(['rider.account', 'stop'])
            ->get();

        $absent = AbsenceNotice::query()
            ->whereIn('rider_id', $seats->pluck('rider_id'))
            ->where('date', $date->toDateString())
            ->pluck('rider_id')
            ->flip();

        return $seats
            ->sortBy(fn (Seat $seat) => [$order[$seat->stop_id] ?? 0, $seat->rider->name])
            ->map(fn (Seat $seat) => [
                'rider_id' => $seat->rider_id,
                'name' => $seat->rider->name,
                'phone' => $seat->rider->account->phone,
                'stop' => $seat->stop->name,
                'time' => $run->timeAtStop($seat->stop_id),
                'absent' => $absent->has($seat->rider_id),
            ])
            ->values();
    }
}
