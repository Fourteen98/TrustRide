<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Modules\Subscriptions\Models\Plan;

class PlanController
{
    /**
     * Plans on sale.
     *
     * Paying in the app comes with Hubtel. Until then the app shows these and tells her how to pay.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('duration_days')->get()),
        ]);
    }
}
