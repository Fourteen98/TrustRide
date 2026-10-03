<?php

namespace App\Api\Rider\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Models\Plan;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            /** In pesewas. 6000 is GHS 60. */
            'price' => $this->price,
            'price_label' => Money::format($this->price),
            'duration_days' => $this->duration_days,
            /** 1 for one trip a day, 2 for there and back */
            'trips_per_day' => $this->trips_per_day,
            'no_show_rule' => $this->no_show_policy->label(),
        ];
    }
}
