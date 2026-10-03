<?php

namespace App\Api\Rider\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Models\Subscription;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->plan->name,
            /** e.g. "GHS 60" */
            'price_label' => Money::format($this->price),
            /** "2026-10-05" */
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            /** Days left including today. 0 if it has not started yet. */
            'days_left' => $this->starts_on->isFuture() ? 0 : (int) max(0, today()->diffInDays($this->ends_on, false) + 1),
            'has_started' => ! $this->starts_on->isFuture(),
            /** Days added because she told us in time she wouldn't ride */
            'credited_days' => $this->credited_days,
            /** A sentence describing what happens to a day she doesn't use */
            'no_show_rule' => $this->no_show_policy->label(),
        ];
    }
}
