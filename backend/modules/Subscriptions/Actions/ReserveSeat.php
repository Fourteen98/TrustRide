<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;

/** Holds a place for a rider on a run. This is where capacity is enforced. */
class ReserveSeat
{
    public function handle(Rider $rider, Run $run, int $stopId, ?RiderApplication $application = null): Seat
    {
        return DB::transaction(function () use ($rider, $run, $stopId, $application) {
            // Lock the run so two operators can't both take the last seat.
            $run = Run::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->is_active) {
                throw new DomainException('That run is not active.');
            }

            $stops = $run->route->stops;

            if (! $stops->contains('id', $stopId)) {
                throw new DomainException('That stop is not on this run\'s route.');
            }

            if ($stops->last()->id === $stopId) {
                throw new DomainException('That stop is the destination. Choose the stop where she gets on or off.');
            }

            $held = Seat::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])
                ->whereHas('run', fn ($query) => $query->where('direction', $run->direction))
                ->exists();

            if ($held) {
                throw new DomainException("{$rider->name} already has a seat in that direction.");
            }

            if ($run->seatsFree() < 1) {
                throw new DomainException("That run is full. All {$run->seats_held} seats are taken.");
            }

            return Seat::query()->create([
                'rider_id' => $rider->id,
                'run_id' => $run->id,
                'stop_id' => $stopId,
                'application_id' => $application?->id,
            ]);
        });
    }
}
