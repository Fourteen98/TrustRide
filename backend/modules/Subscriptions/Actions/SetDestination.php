<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;

/**
 * A rider says where she goes on her route: the stop she gets off at going out, and gets on at coming back.
 * It is set on all her seats on that route at once, since she goes to the same place both ways.
 *
 * It must be further along the route than her own stop. Choosing the end of the route clears it.
 * Seats are not counted per stop, so this never changes whether there is room.
 */
class SetDestination
{
    /** @return int how many of her seats changed */
    public function handle(Seat $seat, int $stopId): int
    {
        $seat->loadMissing('run.route.stops');
        $stops = $seat->run->route->stops;
        $position = $stops->pluck('pivot.position', 'id');

        if (! $position->has($stopId)) {
            throw new DomainException('That stop is not on your route.');
        }

        if ($position[$stopId] <= ($position[$seat->stop_id] ?? -1)) {
            throw new DomainException('Choose a stop after yours, towards '.$stops->last()->name.'.');
        }

        $value = $stopId === $stops->last()->id ? null : $stopId;

        return DB::transaction(fn () => Seat::query()
            ->where('rider_id', $seat->rider_id)
            ->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])
            ->whereHas('run', fn ($run) => $run->where('route_id', $seat->run->route_id))
            ->update(['destination_stop_id' => $value]));
    }
}
