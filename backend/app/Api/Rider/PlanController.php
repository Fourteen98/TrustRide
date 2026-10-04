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
     * Also lists the mobile money networks she can pay from, for `POST purchase`.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('duration_days')->get()),
            'networks' => collect(config('payments.networks'))->map(fn (array $network, string $key) => ['key' => $key, 'label' => $network['label']])->values()->all(),
        ]);
    }
}
