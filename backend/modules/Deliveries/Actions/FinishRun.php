<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;

/**
 * The run is over. Every collected parcel must be marked delivered or not first. Parcels he never collected
 * go back in the queue for the next day.
 */
class FinishRun
{
    /** @return int how many parcels went back in the queue */
    public function handle(DeliveryRun $run): int
    {
        if ($run->status !== RunStatus::Out) {
            throw new DomainException($run->status === RunStatus::Planned ? 'This run hasn\'t started.' : 'This run has already finished.');
        }

        $onBoard = Delivery::query()->where('delivery_run_id', $run->id)->where('status', DeliveryStatus::PickedUp)->count();

        if ($onBoard > 0) {
            throw new DomainException("{$onBoard} ".($onBoard === 1 ? 'parcel is' : 'parcels are').' still with you. Mark each delivered or couldn\'t deliver.');
        }

        return DB::transaction(function () use ($run) {
            $back = Delivery::query()->where('delivery_run_id', $run->id)->where('status', DeliveryStatus::Assigned)
                ->update(['status' => DeliveryStatus::Pending, 'delivery_run_id' => null, 'position' => null, 'delivery_date' => $run->date->addDay()->toDateString()]);

            $run->update(['status' => RunStatus::Done, 'finished_at' => now()]);

            return $back;
        });
    }
}
