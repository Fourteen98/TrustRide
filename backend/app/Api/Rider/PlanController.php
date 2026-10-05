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
     * Also lists the mobile money networks she can pay from. `automatic` is true where direct debit works,
     * so the app can offer `POST auto-renewal` next to `POST purchase`.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('duration_days')->get()),
            /** How many days before a plan ends an automatic payment is taken, to say so before she agrees */
            'auto_renew_charge_days_before' => (int) config('subscriptions.auto_renew_charge_days_before'),
            'networks' => collect(config('payments.networks'))->map(fn (array $network, string $key) => ['key' => $key, 'label' => $network['label'], 'automatic' => (bool) $network['debit_channel']])->values()->all(),
        ]);
    }
}
