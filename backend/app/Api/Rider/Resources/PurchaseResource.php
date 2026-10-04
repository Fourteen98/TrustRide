<?php

namespace App\Api\Rider\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Enums\PurchaseStatus;
use Modules\Subscriptions\Models\PlanPurchase;

/** @mixin PlanPurchase */
class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /**
             * pending: she has been prompted, keep asking. activated: paid and her subscription is running.
             * failed: not paid, she can try again. needs_attention: paid, but we must fix something. Show support.
             *
             * @var 'pending'|'activated'|'failed'|'needs_attention'
             */
            'status' => $this->status->value,
            'plan' => $this->plan->name,
            /** e.g. "GHS 60" */
            'amount_label' => Money::format($this->amount),
            /** e.g. "MTN MoMo" */
            'network' => config("payments.networks.{$this->network}.label", $this->network),
            /** @var string A sentence for her, saying where things stand */
            'message' => match ($this->status) {
                PurchaseStatus::Pending => 'Approve the prompt on your phone with your mobile money PIN.',
                PurchaseStatus::Activated => 'Paid. Your seat is yours.',
                PurchaseStatus::Failed => 'The payment did not go through. You can try again.',
                PurchaseStatus::NeedsAttention => 'We got your payment, but couldn\'t start your subscription. We are fixing it and will message you.',
            },
            'started_at' => $this->created_at->toIso8601String(),
        ];
    }
}
