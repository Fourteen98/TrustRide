<?php

namespace App\Api\Rider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Riders\Actions\CancelDataDeletion;
use Modules\Riders\Actions\RequestDataDeletion;

class DataDeletionController
{
    use ResolvesRider;

    /**
     * Ask for her data to be deleted.
     *
     * Nothing is deleted straight away. An operator confirms it, because it can't be undone and it
     * ends her subscription. Asking twice is harmless.
     */
    public function store(Request $request, RequestDataDeletion $requestDeletion): JsonResponse
    {
        $deletion = $requestDeletion->handle($this->riderOrFail($request));

        return response()->json(['requested_at' => $deletion->requested_at->toIso8601String()], 202);
    }

    /**
     * Take back a deletion request that has not been acted on yet.
     */
    public function destroy(Request $request, CancelDataDeletion $cancel): JsonResponse
    {
        $cancel->handle($this->riderOrFail($request));

        return response()->json(status: 204);
    }
}
