<?php

namespace App\Api\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Payments\Actions\HandleHubtelCallback;
use Throwable;

class HubtelController
{
    /**
     * Hubtel payment callback.
     *
     * Hubtel calls this when a rider answers a mobile money prompt. It is not for the app.
     * It always answers 200, even if something goes wrong on our side, so Hubtel does not keep
     * retrying. Anything missed is picked up by the status check (`payments:reconcile`).
     *
     * @unauthenticated
     */
    public function payment(Request $request, HandleHubtelCallback $handle): JsonResponse
    {
        try {
            $handle->handle($request->all());
        } catch (Throwable $e) {
            Log::channel('hubtel')->error('Callback handling crashed', ['error' => $e->getMessage()]);
            report($e);
        }

        return response()->json(['received' => true]);
    }
}
