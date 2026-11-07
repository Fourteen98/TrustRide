<?php

namespace Modules\Trips\Queries;

use Illuminate\Support\Collection;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/**
 * Today's riders on a run, stop by stop in the order the van reaches them: who gets on at each stop, with what the
 * mate has marked, and who gets off there. Works before the trip starts too, when nothing is marked yet.
 *
 * A rider is on board from when the mate marks her picked up until she says she got down, or the van reaches
 * the stop she gets off at.
 */
class RidersOnTrip
{
    public function __construct(private TodaysRiders $riders) {}

    /**
     * @return Collection<int, array{stop_id: int, name: string, landmark: ?string, time: ?string, reached: bool, riders: list<array{rider_id: int, name: string, absent: bool, on_plan: int, paying_fare: int, gets_off: string, pickup: 'picked_up'|'missed'|null}>, getting_off: list<array{rider_id: int, name: string, people: int, on_board: bool}>}>
     */
    public function handle(Run $run, ?Trip $trip): Collection
    {
        $run->loadMissing('route.stops');
        $today = $this->riders->handle($run);
        $boarding = $today->groupBy('stop_id');
        $pickups = $trip ? $trip->pickups->keyBy('rider_id') : collect();

        $probe = $trip ?? (new Trip)->setRelation('run', $run);
        $reached = $trip ? $probe->orderOf($trip->reached_stop_id) : null;
        $stops = $probe->stopsInOrder();

        // Who gets off where. A rider who isn't coming, or wasn't at her stop, gets off nowhere.
        $alighting = $today
            ->reject(fn (array $rider) => $rider['absent'] || ($pickups[$rider['rider_id']] ?? null)?->status->value === 'missed')
            ->groupBy('off_stop_id');

        return $stops
            ->map(fn ($stop, int $index) => [
                'stop_id' => $stop->id,
                'name' => $stop->name,
                'landmark' => $stop->landmark,
                'time' => $run->timeAtStop($stop->id),
                'reached' => $reached !== null && $index <= $reached,
                'riders' => ($boarding[$stop->id] ?? collect())->map(fn (array $rider) => [
                    'rider_id' => $rider['rider_id'],
                    'name' => $rider['name'],
                    'absent' => $rider['absent'],
                    /** People on her plan, paid */
                    'on_plan' => $rider['on_plan'],
                    /** People she holds seats for today, who each pay the fare */
                    'paying_fare' => $rider['paying_fare'],
                    /** Where she gets off */
                    'gets_off' => $rider['off_stop'],
                    'pickup' => ($pickups[$rider['rider_id']] ?? null)?->status->value,
                ])->values()->all(),
                'getting_off' => ($alighting[$stop->id] ?? collect())->map(fn (array $rider) => [
                    'rider_id' => $rider['rider_id'],
                    'name' => $rider['name'],
                    /** Her and the people with her */
                    'people' => 1 + $rider['on_plan'] + $rider['paying_fare'],
                    'on_board' => $this->isOnBoard($pickups[$rider['rider_id']] ?? null, $index, $reached),
                ])->values()->all(),
            ])
            // A stop where nobody gets on or off is still shown, so the crew sees the whole road. Only the end, with nobody, is dropped.
            ->filter(fn (array $stop, int $index) => $stop['riders'] !== [] || $stop['getting_off'] !== [] || $index < $stops->count() - 1)
            ->values();
    }

    /**
     * Everyone on the van now, in the order they get off.
     *
     * @return list<array{rider_id: int, name: string, people: int, gets_off: string, off_time: ?string}>
     */
    public function onBoard(Run $run, ?Trip $trip): array
    {
        if ($trip === null || ! $trip->isRunning()) {
            return [];
        }

        $reached = $trip->orderOf($trip->reached_stop_id);
        $pickups = $trip->pickups->keyBy('rider_id');

        return $this->riders->handle($run)
            ->filter(fn (array $rider) => $this->isOnBoard($pickups[$rider['rider_id']] ?? null, $trip->orderOf($rider['off_stop_id']) ?? PHP_INT_MAX, $reached))
            ->sortBy(fn (array $rider) => $trip->orderOf($rider['off_stop_id']))
            ->map(fn (array $rider) => [
                'rider_id' => $rider['rider_id'],
                'name' => $rider['name'],
                'people' => 1 + $rider['on_plan'] + $rider['paying_fare'],
                'gets_off' => $rider['off_stop'],
                'off_time' => $rider['off_time'],
            ])
            ->values()->all();
    }

    /** True if this rider is due on the run today and hasn't said she won't ride. */
    public function expects(Run $run, int $riderId): ?array
    {
        return $this->riders->handle($run)->first(fn (array $rider) => $rider['rider_id'] === $riderId && ! $rider['absent']);
    }

    /** Picked up, hasn't said she got down, and the van hasn't yet reached the stop she gets off at. */
    private function isOnBoard(?TripPickup $pickup, int $offIndex, ?int $reached): bool
    {
        return $pickup?->status->value === 'picked_up' && $pickup->alighted_at === null && $offIndex > ($reached ?? -1);
    }
}
