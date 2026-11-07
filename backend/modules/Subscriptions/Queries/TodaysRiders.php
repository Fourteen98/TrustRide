<?php

namespace Modules\Subscriptions\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\GuestSeats;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * Who a run carries on a given day, in the order the van picks them up, with where each gets on and off.
 * A rider is on the list if her seat is active and her subscription covers the day.
 */
class TodaysRiders
{
    /**
     * `on_plan` is how many people travel with her on her plan, paid. `paying_fare` is how many she has held seats for
     * that day, who each pay the mate.
     *
     * `stop_id` and `time` are where and when she gets on, `off_stop_id` and `off_time` where and when she gets off.
     *
     * @return Collection<int, array{rider_id: int, name: string, phone: string, channel: ?string, stop_id: int, stop: string, time: ?string, off_stop_id: int, off_stop: string, off_time: ?string, absent: bool, on_plan: int, paying_fare: int}>
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
            ->with(['rider.account'])
            ->get()
            ->each(fn (Seat $seat) => $seat->setRelation('run', $run));

        $stops = $run->route->stops->keyBy('id');
        // The order the van reaches them, which is backwards on the way back.
        $reach = fn (int $stopId) => ($run->direction->value === 'outbound' ? 1 : -1) * ($order[$stopId] ?? 0);

        $absent = AbsenceNotice::query()
            ->whereIn('rider_id', $seats->pluck('rider_id'))
            ->where('date', $date->toDateString())
            // Only a day she told us about. A day given back because the mate missed her still has her on the list.
            ->where('source', 'rider')
            ->pluck('rider_id')
            ->flip();

        $guests = GuestSeats::query()->where('run_id', $run->id)->where('date', $date->toDateString())->pluck('seats', 'rider_id');

        return $seats
            ->sortBy(fn (Seat $seat) => [$reach($seat->boardsAt()), $seat->rider->name])
            ->map(fn (Seat $seat) => [
                'rider_id' => $seat->rider_id,
                'name' => $seat->rider->name,
                'phone' => $seat->rider->account->phone,
                'channel' => $seat->rider->account->preferred_channel,
                'stop_id' => $seat->boardsAt(),
                'stop' => $stops[$seat->boardsAt()]->name,
                'time' => $seat->pickupTime(),
                'off_stop_id' => $seat->getsOffAt(),
                'off_stop' => $stops[$seat->getsOffAt()]->name,
                'off_time' => $seat->dropTime(),
                'absent' => $absent->has($seat->rider_id),
                'on_plan' => max(0, $seat->party - 1),
                'paying_fare' => $absent->has($seat->rider_id) ? 0 : (int) ($guests[$seat->rider_id] ?? 0),
            ])
            ->values();
    }
}
