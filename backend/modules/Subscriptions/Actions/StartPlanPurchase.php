<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Modules\Payments\Actions\StartPayment;
use Modules\Payments\Models\PaymentMandate;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\PurchaseStatus;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPurchase;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * A rider buys or renews a plan. She gets a mobile money prompt on the phone she names, or,
 * when a mandate is passed, the money is taken by direct debit with no prompt.
 * Nothing is activated here. That happens when Payments says the money arrived.
 *
 * Everything that would stop the subscription being activated is checked first, before money is asked for.
 */
class StartPlanPurchase
{
    public function __construct(private ActivateSubscription $activate, private StartPayment $pay) {}

    public function handle(Rider $rider, Plan $plan, string $phone, string $network, ?PaymentMandate $mandate = null, ?int $seats = null): PlanPurchase
    {
        // How many the plan is for, her included. Unless she says, what her seat holds now, so a renewal keeps it.
        $seats ??= max(1, (int) Seat::query()->where('rider_id', $rider->id)->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])->max('party'));
        $this->activate->ensureAllowed($rider, $plan, $seats);

        // One prompt at a time. After the waiting time she may try again even if the first is unresolved.
        $waiting = PlanPurchase::query()
            ->where('rider_id', $rider->id)
            ->where('status', PurchaseStatus::Pending)
            ->where('created_at', '>', now()->subMinutes((int) config('payments.fail_after_minutes')))
            ->exists();

        if ($waiting) {
            throw new DomainException('A payment is already waiting. Approve the prompt on your phone, or wait a few minutes and try again.');
        }

        // One renewal ahead is enough. A second would only lock her money up.
        $ahead = Subscription::query()
            ->where('rider_id', $rider->id)
            ->where('status', SubscriptionStatus::Active)
            ->where('starts_on', '>', today()->toDateString())
            ->exists();

        if ($ahead) {
            throw new DomainException('You have already paid for your next subscription.');
        }

        $purchase = PlanPurchase::query()->create([
            'rider_id' => $rider->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price * $seats,
            'seats' => $seats,
            'network' => $network,
        ]);

        try {
            $payment = $this->pay->handle(PlanPurchase::PAYABLE, $purchase->id, $purchase->amount, $phone, $network, $seats > 1 ? "Aveo {$plan->name} plan for {$seats}" : "Aveo {$plan->name} plan", $mandate);
        } catch (DomainException $e) {
            $purchase->update(['status' => PurchaseStatus::Failed]);

            throw $e;
        }

        $purchase->update(['payment_id' => $payment->id]);

        return $purchase;
    }
}
