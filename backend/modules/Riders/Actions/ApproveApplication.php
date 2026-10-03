<?php

namespace Modules\Riders\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RiderStatus;
use Modules\Riders\Events\ApplicationApproved;
use Modules\Riders\Models\RiderApplication;

/**
 * Marks an application approved. Approving only makes sense with a seat, so the console goes through
 * Subscriptions' ApproveApplicationWithSeats, which reserves the seat and then calls this.
 */
class ApproveApplication
{
    public function handle(RiderApplication $application, Staff $by): RiderApplication
    {
        if (! $application->isPending()) {
            throw new DomainException('This application has already been reviewed.');
        }

        $application->update(['status' => ApplicationStatus::Approved, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
        $application->rider->update(['status' => RiderStatus::Approved]);

        ApplicationApproved::dispatch($application);

        return $application;
    }
}
