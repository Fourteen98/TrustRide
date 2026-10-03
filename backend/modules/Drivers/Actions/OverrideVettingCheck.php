<?php

namespace Modules\Drivers\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\VettingCheck;

/** Lets a failed check through. The record keeps who did it and why. */
class OverrideVettingCheck
{
    public function handle(VettingCheck $check, Staff $by, string $reason): VettingCheck
    {
        if ($check->result !== VettingResult::Failed) {
            throw new DomainException('Only a failed check can be overridden.');
        }

        if (trim($reason) === '') {
            throw new DomainException('An override needs a reason.');
        }

        $check->update(['overridden_by' => $by->id, 'override_reason' => trim($reason), 'overridden_at' => now()]);

        return $check;
    }
}
