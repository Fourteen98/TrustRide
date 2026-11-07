<?php

namespace Modules\Deliveries\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Deliveries\Enums\LimitPolicy;

/** A monthly plan a business can be put on. Its numbers are copied to the business, so changing it moves nobody. */
class DeliveryPlan extends Model
{
    protected $fillable = ['name', 'monthly_fee', 'delivery_allowance', 'overage_fee', 'at_limit', 'is_active'];

    protected $attributes = ['at_limit' => 'overage', 'overage_fee' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'integer',
            'delivery_allowance' => 'integer',
            'overage_fee' => 'integer',
            'at_limit' => LimitPolicy::class,
            'is_active' => 'boolean',
        ];
    }
}
