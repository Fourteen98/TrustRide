<?php

namespace Modules\Trips\Actions;

use App\Support\Recipient;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Events\RiderMissed;
use Modules\Trips\Events\RiderPickedUp;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;
use Modules\Trips\Queries\RidersOnTrip;

/**
 * The mate says a rider got on, or was not at her stop. It can be changed while the trip runs,
 * because a thumb slips. Marking anyone at a stop also tells us the van has reached that stop.
 */
class MarkPickup
{
    public function __construct(private RidersOnTrip $riders) {}

    public function handle(Trip $trip, int $riderId, PickupStatus $status, ?Account $by = null): TripPickup
    {
        if (! $trip->isRunning()) {
            throw new DomainException('Start the trip first.');
        }

        $rider = $this->riders->expects($trip->run, $riderId) ?? throw new DomainException('She is not riding on this run today.');

        return DB::transaction(function () use ($trip, $rider, $status, $by) {
            $pickup = TripPickup::query()->where('trip_id', $trip->id)->where('rider_id', $rider['rider_id'])->lockForUpdate()->first();
            $changed = $pickup?->status !== $status;

            $pickup = TripPickup::query()->updateOrCreate(
                ['trip_id' => $trip->id, 'rider_id' => $rider['rider_id']],
                ['stop_id' => $rider['stop_id'], 'status' => $status, 'marked_by_account' => $by?->id, 'marked_at' => now()],
            );

            if (($trip->orderOf($rider['stop_id']) ?? -1) > ($trip->orderOf($trip->reached_stop_id) ?? -1)) {
                $trip->update(['reached_stop_id' => $rider['stop_id']]);
            }

            if ($changed) {
                $event = $status === PickupStatus::PickedUp ? RiderPickedUp::class : RiderMissed::class;
                $event::dispatch(
                    $trip,
                    [new Recipient('rider', $rider['phone'], $rider['channel'], $rider['name'])],
                    ['rider_id' => $rider['rider_id'], 'rider_name' => $rider['name'], 'stop' => $rider['stop']],
                );
            }

            return $pickup;
        });
    }
}
