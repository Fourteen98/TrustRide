<?php

namespace Modules\Riders\Actions;

use Modules\Riders\Models\DataDeletionRequest;
use Modules\Riders\Models\Rider;

/** She changed her mind before an operator acted. */
class CancelDataDeletion
{
    public function handle(Rider $rider): void
    {
        DataDeletionRequest::query()
            ->where('rider_id', $rider->id)
            ->where('status', DataDeletionRequest::PENDING)
            ->update(['status' => DataDeletionRequest::CANCELLED]);
    }
}
