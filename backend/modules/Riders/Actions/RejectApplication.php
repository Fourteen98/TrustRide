<?php

namespace Modules\Riders\Actions;

use DomainException;
use Modules\Access\Models\Staff;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RejectionReason;
use Modules\Riders\Events\ApplicationRejected;
use Modules\Riders\Models\RiderApplication;

class RejectApplication
{
    public function handle(RiderApplication $application, Staff $by, RejectionReason $reason, ?string $note = null): RiderApplication
    {
        if (! $application->isPending()) {
            throw new DomainException('This application has already been reviewed.');
        }

        if ($reason === RejectionReason::Other && trim((string) $note) === '') {
            throw new DomainException('Say why, so we can tell her.');
        }

        $application->update([
            'status' => ApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'rejection_note' => $note,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ]);

        ApplicationRejected::dispatch($application);

        return $application;
    }
}
