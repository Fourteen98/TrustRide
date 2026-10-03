<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingCheck;
use Modules\Drivers\Models\VettingItem;

/** Records one checklist item as passed or failed. Recording again replaces the result and clears any override. */
class RecordVettingCheck
{
    public function handle(Driver|Vehicle $subject, VettingItem $item, VettingResult $result, Staff $by, ?string $notes = null): VettingCheck
    {
        $expected = $subject instanceof Driver ? VettingItem::DRIVER : VettingItem::VEHICLE;

        if ($item->applies_to !== $expected) {
            throw new DomainException("\"{$item->label}\" is not a check for a {$expected}.");
        }

        return VettingCheck::query()->updateOrCreate(
            [
                'vetting_item_id' => $item->id,
                'driver_id' => $subject instanceof Driver ? $subject->id : null,
                'vehicle_id' => $subject instanceof Vehicle ? $subject->id : null,
            ],
            [
                'result' => $result,
                'notes' => $notes,
                'checked_by' => $by->id,
                'checked_at' => now(),
                'overridden_by' => null,
                'override_reason' => null,
                'overridden_at' => null,
            ],
        );
    }
}
