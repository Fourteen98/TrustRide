<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Deliveries\Models\DeliveryRun;

/** The driver sets off on his run. From now he can collect parcels at each business. */
class StartRun
{
    public function handle(DeliveryRun $run): DeliveryRun
    {
        if ($run->status !== RunStatus::Planned) {
            throw new DomainException('This run has already started.');
        }

        if ($run->date->startOfDay()->gt(today())) {
            throw new DomainException("This run is for {$run->date->format('D j M')}. It can't start before then.");
        }

        $run->update(['status' => RunStatus::Out, 'started_at' => now()]);

        return $run;
    }
}
