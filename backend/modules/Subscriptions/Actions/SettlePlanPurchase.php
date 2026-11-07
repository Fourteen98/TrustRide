<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Subscriptions\Enums\PurchaseStatus;
use Modules\Subscriptions\Events\PurchaseFailed;
use Modules\Subscriptions\Events\PurchaseNeedsAttention;
use Modules\Subscriptions\Models\PlanPurchase;

/**
 * Finishes a purchase once Payments has said what happened to the money.
 * Safe to call twice: a purchase that is already activated stays as it is.
 */
class SettlePlanPurchase
{
    public function __construct(private ActivateSubscription $activate) {}

    /**
     * The money arrived. Start the subscription.
     *
     * If it can't be started (the plan was withdrawn or her seat was released while she was paying),
     * the purchase is flagged for an operator. She has paid, so it is never quietly dropped.
     */
    public function paid(PlanPurchase $purchase, ?Staff $by = null): PlanPurchase
    {
        return DB::transaction(function () use ($purchase, $by) {
            $purchase = PlanPurchase::query()->lockForUpdate()->with(['rider', 'plan'])->findOrFail($purchase->id);

            if ($purchase->status === PurchaseStatus::Activated) {
                return $purchase;
            }

            try {
                // Its own transaction inside ours, so a refusal undoes only the activation.
                $subscription = DB::transaction(fn () => $this->activate->handle($purchase->rider, $purchase->plan, $by, seats: $purchase->seats));
            } catch (DomainException $e) {
                $purchase->update(['status' => PurchaseStatus::NeedsAttention, 'problem' => str($e->getMessage())->limit(250)->toString()]);
                PurchaseNeedsAttention::dispatch($purchase);

                return $purchase;
            }

            $purchase->update(['status' => PurchaseStatus::Activated, 'subscription_id' => $subscription->id, 'problem' => null]);
            $purchase->autoRenewal?->update(['failures' => 0, 'last_error' => null]);

            return $purchase;
        });
    }

    /** The money did not come. */
    public function failed(PlanPurchase $purchase): PlanPurchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = PlanPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === PurchaseStatus::Pending) {
                $purchase->update(['status' => PurchaseStatus::Failed]);
                PurchaseFailed::dispatch($purchase);

                // A charge we started ourselves counts against her automatic renewal.
                if ($purchase->autoRenewal?->isActive()) {
                    app(ChargeAutoRenewal::class)->failed($purchase->autoRenewal, 'The charge did not go through.');
                }
            }

            return $purchase;
        });
    }
}
