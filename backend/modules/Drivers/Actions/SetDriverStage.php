<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\DriverStage;
use Modules\Drivers\Models\Driver;

/**
 * Moves a driver along the way from daily contract to driver-partner, or back.
 *
 * Moving up needs an approved driver with at least one monthly assessment on record, since assessments are what
 * decide it. Moving back down is always allowed. The reason is kept with who made the change.
 */
class SetDriverStage
{
    public function handle(Driver $driver, DriverStage $stage, Staff $by, ?string $note = null): Driver
    {
        if ($stage === $driver->stage) {
            return $driver;
        }

        $up = $stage !== DriverStage::DailyContract;

        if ($up && ! $driver->isApproved()) {
            throw new DomainException('Only an approved driver can move up from a daily contract.');
        }

        if ($up && ! $driver->statements()->whereNotNull('assessment_outcome')->exists()) {
            throw new DomainException('Record a monthly assessment for him first. Assessments decide who moves up.');
        }

        $driver->update([
            'stage' => $stage,
            'stage_changed_at' => now(),
            'stage_changed_by' => $by->id,
            'stage_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        return $driver;
    }
}
