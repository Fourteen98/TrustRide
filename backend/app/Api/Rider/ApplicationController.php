<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\ApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Actions\WithdrawApplication;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Subscriptions\Queries\RouteChoices;

class ApplicationController
{
    use ResolvesRider;

    /**
     * Routes she can apply for.
     *
     * Each route comes with the stops she can use and the times a van actually runs.
     * A time with `seats_free: false` can still be asked for. An operator decides.
     */
    public function routes(RouteChoices $choices): JsonResponse
    {
        return response()->json(['routes' => $choices->handle()]);
    }

    /**
     * Apply for a seat.
     *
     * Give a morning time, an evening time, or both. Only one application can be waiting at a time.
     */
    public function store(Request $request, SubmitApplication $submit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'route_id' => ['required', 'integer'],
            'stop_id' => ['required', 'integer'],
            /** "06:30" */
            'morning_time' => ['nullable', 'date_format:H:i'],
            'evening_time' => ['nullable', 'date_format:H:i'],
        ]);

        $application = $submit->handle($request->user(), $data);

        return response()->json(['application' => new ApplicationResource($application->load(['route', 'stop']))], 201);
    }

    /**
     * Withdraw the application that is waiting.
     */
    public function destroy(Request $request, WithdrawApplication $withdraw): JsonResponse
    {
        $application = $this->riderOrFail($request)->applications()->where('status', ApplicationStatus::Pending)->latest('id')->first();

        if ($application) {
            $withdraw->handle($application);
        }

        return response()->json(status: 204);
    }
}
