<?php

namespace Modules\Subscriptions\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payments\Actions\RequestMandate;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Models\AutoRenewal;
use Modules\Subscriptions\Models\Plan;

/**
 * A rider asks to pay by direct debit: approve once, and her plan's price is taken each time it ends.
 * She gets a request on her phone. Nothing is charged here. When she approves, the first charge
 * follows at once if she has no running subscription, and otherwise when the current one is ending.
 *
 * Asking again, with another plan or another wallet, replaces what was there.
 */
class StartAutoRenewal
{
    public function __construct(private ActivateSubscription $activate, private RequestMandate $mandates) {}

    public function handle(Rider $rider, Plan $plan, string $phone, string $network): AutoRenewal
    {
        $this->activate->ensureAllowed($rider, $plan);

        return DB::transaction(function () use ($rider, $plan, $phone, $network) {
            $renewal = AutoRenewal::query()->where('rider_id', $rider->id)->lockForUpdate()->first()
                ?? AutoRenewal::query()->create(['rider_id' => $rider->id, 'plan_id' => $plan->id]);

            // Throws, and rolls this back, if the gateway refuses or the network has no direct debit.
            $mandate = $this->mandates->handle(AutoRenewal::OWNER, $renewal->id, $phone, $network);

            $renewal->update([
                'plan_id' => $plan->id,
                'mandate_id' => $mandate->id,
                'status' => AutoRenewalStatus::Pending,
                'failures' => 0,
                'last_error' => null,
                'last_attempt_on' => null,
            ]);

            return $renewal;
        });
    }
}
