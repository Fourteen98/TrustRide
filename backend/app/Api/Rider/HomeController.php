<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\ApplicationResource;
use App\Api\Rider\Resources\NoticeResource;
use App\Api\Rider\Resources\SeatResource;
use App\Api\Rider\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Riders\Models\DataDeletionRequest;
use Modules\Subscriptions\Queries\RiderOverview;

class HomeController
{
    use ResolvesRider;

    /**
     * Everything the rider's home screen shows.
     *
     * One call. The app decides what to show from what comes back: no application yet, an application
     * waiting, a seat reserved and waiting for payment, or a seat with a running subscription.
     */
    public function show(Request $request, RiderOverview $overview): JsonResponse
    {
        $rider = $this->rider($request);
        $application = $rider?->applications()->with(['route', 'stop'])->latest('id')->first();
        $data = $rider ? $overview->handle($rider) : null;

        return response()->json([
            /** Null until she has applied once */
            'rider' => $rider ? ['name' => $rider->name, 'status' => $rider->status->value] : null,
            /** Her most recent application, whatever its status */
            'application' => $application ? new ApplicationResource($application) : null,
            'seats' => SeatResource::collection($data['seats'] ?? []),
            /** The subscription running now, or the next one to start */
            'subscription' => isset($data['subscription']) ? new SubscriptionResource($data['subscription']) : null,
            /** A renewal already bought, which starts when the current one ends */
            'renewal' => isset($data['renewal']) ? new SubscriptionResource($data['renewal']) : null,
            /** Days she has already said she won't ride */
            'notices' => NoticeResource::collection($data['notices'] ?? []),
            /**
             * The next days she can still say she won't ride, as "2026-10-07"
             *
             * @var list<string>
             */
            'ride_dates' => $data['ride_dates'] ?? [],
            /** @var bool True if she has asked for her data to be deleted and nobody has acted yet */
            'deletion_requested' => $rider !== null && DataDeletionRequest::query()
                ->where('rider_id', $rider->id)->where('status', DataDeletionRequest::PENDING)->exists(),
            'support' => [
                /** @var string|null */
                'phone' => config('trustride.support.phone'),
                /** @var string|null */
                'whatsapp' => config('trustride.support.whatsapp'),
            ],
        ]);
    }
}
