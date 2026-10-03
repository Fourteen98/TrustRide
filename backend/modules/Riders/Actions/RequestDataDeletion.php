<?php

namespace Modules\Riders\Actions;

use Modules\Riders\Models\DataDeletionRequest;
use Modules\Riders\Models\Rider;

/**
 * A rider asks for her data to be deleted. Nothing is deleted yet. It can't be undone,
 * so an operator confirms it in the console. Asking twice returns the same request.
 */
class RequestDataDeletion
{
    public function handle(Rider $rider): DataDeletionRequest
    {
        return DataDeletionRequest::query()->firstOrCreate(
            ['rider_id' => $rider->id, 'status' => DataDeletionRequest::PENDING],
            ['requested_at' => now()],
        );
    }
}
