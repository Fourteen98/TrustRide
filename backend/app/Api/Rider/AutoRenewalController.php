<?php

namespace App\Api\Rider;

use App\Api\Rider\Resources\AutoRenewalResource;
use App\Support\Phone;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Payments\Actions\ConfirmMandate;
use Modules\Subscriptions\Actions\StartAutoRenewal;
use Modules\Subscriptions\Actions\StopAutoRenewal;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\Plan;

/**
 * Paying by direct debit. She approves once, and her plan's price is taken each time it ends.
 * This is the other way to pay, next to `POST purchase`, which prompts her every time.
 */
class AutoRenewalController
{
    use ResolvesRider;

    /**
     * Her automatic renewal.
     *
     * Null if she has never set one up. Ask every few seconds while the status is `pending`.
     * Once it is `active`, ask `GET purchase` for the first charge.
     */
    public function show(Request $request): JsonResponse
    {
        $renewal = $this->find($request);

        return response()->json(['auto_renewal' => $renewal ? new AutoRenewalResource($renewal) : null]);
    }

    /**
     * Set up automatic renewal.
     *
     * Sends a direct debit request to her phone. Nothing is charged by this call. When she approves,
     * the plan is charged at once if she has no running subscription, and otherwise a day before it ends.
     * Only networks marked `automatic` under `GET plans` can be used. Calling it again replaces the plan or the wallet.
     */
    public function store(Request $request, StartAutoRenewal $start): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'network' => ['required', Rule::in(array_keys(config('payments.networks')))],
            /** The wallet to charge. Leave out to use the number she logs in with. */
            'phone' => ['nullable', 'string'],
            /** How many people each charge is for, her included. Only above 1 on vans that add people to her plan. */
            'seats' => ['nullable', 'integer', 'min:1', 'max:9'],
        ]);

        $plan = Plan::query()->where('is_active', true)->find($data['plan_id']) ?? throw new DomainException('That plan is no longer sold.');

        if (! empty($data['phone']) && ! Phone::isValid($data['phone'])) {
            throw new DomainException('Enter a valid Ghana phone number.');
        }

        $phone = empty($data['phone']) ? $request->user()->phone : Phone::normalise($data['phone']);

        $renewal = $start->handle($this->riderOrFail($request), $plan, $phone, $data['network'], $data['seats'] ?? null);

        return response()->json(['auto_renewal' => new AutoRenewalResource($renewal->load(['plan', 'mandate']))], 201);
    }

    /**
     * Send the code she was texted.
     *
     * Only needed when `needs_code` is true. The status changes a moment later, so keep asking `GET auto-renewal`.
     */
    public function code(Request $request, ConfirmMandate $confirm): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $renewal = $this->find($request);

        if (! $renewal?->mandate) {
            throw new DomainException('There is nothing waiting for a code.');
        }

        $confirm->handle($renewal->mandate, $data['code']);

        return response()->json(['auto_renewal' => new AutoRenewalResource($renewal->fresh(['plan', 'mandate']))]);
    }

    /**
     * Stop automatic renewal.
     *
     * The direct debit is withdrawn and nothing more is taken. Her running subscription is not touched.
     */
    public function destroy(Request $request, StopAutoRenewal $stop): JsonResponse
    {
        if ($renewal = $this->find($request)) {
            $stop->handle($renewal);
        }

        return response()->json(['auto_renewal' => $renewal ? new AutoRenewalResource($renewal->fresh(['plan', 'mandate'])) : null]);
    }

    private function find(Request $request): ?AutoRenewal
    {
        $rider = $this->rider($request);

        return $rider ? AutoRenewal::query()->where('rider_id', $rider->id)->with(['plan', 'mandate'])->first() : null;
    }
}
