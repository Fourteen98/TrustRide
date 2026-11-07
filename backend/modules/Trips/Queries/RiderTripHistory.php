<?php

namespace Modules\Trips\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/**
 * The trips a rider was booked on, newest first: the runs she held a seat on, on days her subscription covered.
 * For each one, whether the mate marked her on board or not at the stop, or whether she had said she would not ride.
 *
 * Trips stay after their location history is pruned, so this goes back as far as the trips table does.
 */
class RiderTripHistory
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function handle(int $riderId, int $perPage = 20): LengthAwarePaginator
    {
        $seats = Seat::query()->where('rider_id', $riderId)->with('stop')->latest('id')->get();
        $covered = Subscription::query()->where('rider_id', $riderId)->get(['starts_on', 'ends_on']);

        $page = Trip::query()
            ->whereIn('run_id', $seats->pluck('run_id')->unique())
            ->where(function ($query) use ($covered) {
                // Only the days she was paying for. Before she joined, or after she left, the van was not hers.
                $query->whereRaw('1 = 0');

                foreach ($covered as $subscription) {
                    $query->orWhereBetween('date', [$subscription->starts_on->toDateString(), $subscription->ends_on->toDateString()]);
                }
            })
            ->with(['run.route', 'run.vehicle'])
            ->orderByDesc('date')
            ->orderByDesc('started_at')
            ->paginate($perPage);

        $trips = collect($page->items());
        $pickups = TripPickup::query()->where('rider_id', $riderId)->whereIn('trip_id', $trips->pluck('id'))->with('alightedStop')->get()->keyBy('trip_id');
        $absent = $this->absentDates($riderId, $trips);

        return $page->through(function (Trip $trip) use ($seats, $pickups, $absent) {
            $seat = $seats->firstWhere('run_id', $trip->run_id);
            $pickup = $pickups->get($trip->id);
            $date = $trip->date->toDateString();

            return [
                'date' => $date,
                'route' => $trip->run->route->name,
                'direction' => $trip->run->direction->value,
                'car_number' => $trip->run->vehicle->registration_number,
                'my_stop' => $seat?->stop->name,
                'status' => $trip->status->value,
                'outcome' => match (true) {
                    $pickup !== null => $pickup->status->value,
                    isset($absent[$date]) => 'not_riding',
                    default => 'not_marked',
                },
                'picked_up_at' => $pickup?->status->value === 'picked_up' ? $pickup->marked_at->toIso8601String() : null,
                'alighted_at' => $pickup?->alighted_at?->toIso8601String(),
                'alighted_at_stop' => $pickup?->alightedStop?->name,
                'started_at' => $trip->started_at->toIso8601String(),
                'ended_at' => $trip->ended_at?->toIso8601String(),
            ];
        });
    }

    /**
     * @param  Collection<int, Trip>  $trips
     * @return array<string, true>
     */
    private function absentDates(int $riderId, Collection $trips): array
    {
        if ($trips->isEmpty()) {
            return [];
        }

        return AbsenceNotice::query()
            ->where('rider_id', $riderId)
            ->whereIn('date', $trips->map(fn (Trip $trip) => $trip->date->toDateString())->unique()->values())
            ->get(['date'])
            ->mapWithKeys(fn (AbsenceNotice $notice) => [$notice->date->toDateString() => true])
            ->all();
    }
}
