<?php

namespace Modules\Riders\Actions;

use DomainException;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Events\ApplicationWithdrawn;
use Modules\Riders\Models\RiderApplication;

class WithdrawApplication
{
    public function handle(RiderApplication $application): RiderApplication
    {
        if (! $application->isPending()) {
            throw new DomainException('Only an application that is still waiting can be withdrawn.');
        }

        $application->update(['status' => ApplicationStatus::Withdrawn]);

        ApplicationWithdrawn::dispatch($application);

        return $application;
    }
}
