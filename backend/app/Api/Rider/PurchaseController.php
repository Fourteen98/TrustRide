<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\PurchaseResource;
use App\Support\Phone;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Actions\StartPlanPurchase;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPurchase;

class PurchaseController
{
    use ResolvesRider;

    /**
     * Pay for a plan.
     *
     * Sends a mobile money prompt to her phone. The reply only says the prompt was sent, never that she paid.
     * Then ask `GET purchase` every few seconds until the status is no longer `pending`.
     * She needs a reserved seat first. A renewal starts the day after her current subscription ends.
     */
    public function store(Request $request, StartPlanPurchase $start): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            /** One of the keys from `GET plans` under `networks` */
            'network' => ['required', Rule::in(array_keys(config('payments.networks')))],
            /** The wallet to charge. Leave out to use the number she logs in with. */
            'phone' => ['nullable', 'string'],
            /** How many people the plan is for, her included. Only above 1 on vans that add people to her plan. Leave out to keep what her seat holds. */
            'seats' => ['nullable', 'integer', 'min:1', 'max:9'],
        ]);

        $plan = Plan::query()->where('is_active', true)->find($data['plan_id']) ?? throw new DomainException('That plan is no longer sold.');

        if (! empty($data['phone']) && ! Phone::isValid($data['phone'])) {
            throw new DomainException('Enter a valid Ghana phone number.');
        }

        $phone = empty($data['phone']) ? $request->user()->phone : Phone::normalise($data['phone']);

        $purchase = $start->handle($this->riderOrFail($request), $plan, $phone, $data['network'], seats: $data['seats'] ?? null);

        return response()->json(['purchase' => new PurchaseResource($purchase->load('plan'))], 201);
    }

    /**
     * Her latest payment.
     *
     * Ask this every few seconds while the status is `pending`. Null if she has never tried to pay.
     */
    public function show(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $purchase = $rider ? PlanPurchase::query()->where('rider_id', $rider->id)->with('plan')->latest('id')->first() : null;

        return response()->json(['purchase' => $purchase ? new PurchaseResource($purchase) : null]);
    }
}
