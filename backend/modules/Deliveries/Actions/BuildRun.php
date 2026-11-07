<?php

namespace Modules\Deliveries\Actions;

use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Deliveries\Models\Zone;
use Modules\Drivers\Enums\WorkType;
use Modules\Drivers\Models\Driver;

/**
 * The batch: every parcel waiting for one zone on one day goes on one run for one driver. Parcels are grouped by
 * business, so he collects each shop's together. An operator does this for now; doing it at the cut-off by
 * itself can come later.
 *
 * A zone can have more than one run a day: a second run takes whatever arrived after the first was built.
 */
class BuildRun
{
    public function handle(Zone $zone, CarbonInterface $date, Driver $driver, ?Staff $by = null, ?int $pay = null): DeliveryRun
    {
        if (! $driver->isApproved()) {
            throw new DomainException("{$driver->name} is not an approved driver.");
        }

        if (! $driver->does(WorkType::Ox)) {
            throw new DomainException("{$driver->name} doesn't do Ox work. Tick Ox under the work he does first.");
        }

        return DB::transaction(function () use ($zone, $date, $driver, $by, $pay) {
            $waiting = Delivery::query()
                ->where('zone_id', $zone->id)
                ->whereDate('delivery_date', $date->toDateString())
                ->where('status', DeliveryStatus::Pending)
                ->orderBy('business_id')->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($waiting->isEmpty()) {
                throw new DomainException("No parcels are waiting for {$zone->name} on {$date->format('D j M')}.");
            }

            $run = DeliveryRun::create([
                'zone_id' => $zone->id,
                'date' => $date->toDateString(),
                'driver_id' => $driver->id,
                'driver_pay' => $pay ?? (int) config('deliveries.default_run_pay'),
                'created_by' => $by?->id,
            ]);

            $waiting->values()->each(fn (Delivery $delivery, int $index) => $delivery->update([
                'status' => DeliveryStatus::Assigned,
                'delivery_run_id' => $run->id,
                'position' => $index + 1,
            ]));

            return $run;
        });
    }
}
