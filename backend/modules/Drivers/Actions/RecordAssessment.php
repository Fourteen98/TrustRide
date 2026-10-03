<?php

namespace Modules\Drivers\Actions;

use Carbon\CarbonInterface;
use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\AssessmentOutcome;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\DriverStatement;

/**
 * Records a driver's assessment for a month. This opens his statement for that month if there isn't one,
 * taking the commission from his record as it stands today.
 */
class RecordAssessment
{
    public function handle(Driver $driver, CarbonInterface $month, AssessmentOutcome $outcome, Staff $by, ?string $notes = null, int $bonus = 0): DriverStatement
    {
        $statement = DriverStatement::query()->firstOrNew([
            'driver_id' => $driver->id,
            'period' => $month->copy()->startOfMonth()->toDateString(),
        ]);

        if ($statement->isPaid()) {
            throw new DomainException('That month has already been paid. It can\'t be changed.');
        }

        if (! $statement->exists) {
            $statement->commission_amount = $driver->monthly_commission;
        }

        $statement->fill([
            'assessment_outcome' => $outcome,
            'assessment_notes' => $notes,
            'bonus_amount' => max(0, $bonus),
            'assessed_by' => $by->id,
            'assessed_at' => now(),
        ])->save();

        return $statement;
    }
}
