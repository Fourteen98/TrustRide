<?php

namespace Modules\Drivers\Actions;

use Illuminate\Support\Collection;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingItem;

/** The required checklist items a driver or vehicle has not cleared: not checked yet, or failed without an override. */
class OutstandingVetting
{
    /** @return Collection<int, VettingItem> */
    public function handle(Driver|Vehicle $subject): Collection
    {
        $checks = $subject->vettingChecks()->get()->keyBy('vetting_item_id');

        return VettingItem::query()->for($subject)->where('is_required', true)->get()
            ->reject(fn (VettingItem $item) => $checks->get($item->id)?->isClear() ?? false)
            ->values();
    }
}
