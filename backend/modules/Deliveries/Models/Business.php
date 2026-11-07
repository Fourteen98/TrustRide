<?php

namespace Modules\Deliveries\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Deliveries\Enums\BusinessStatus;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Enums\LimitPolicy;

/** A shop or seller that sends parcels with Aveo Ox, on a monthly plan. */
class Business extends Model
{
    use BelongsToOrganisation;
    use HasUlids;

    protected $fillable = [
        'organisation_id', 'name', 'contact_name', 'phone', 'email', 'pickup_address', 'pickup_landmark',
        'delivery_plan_id', 'monthly_fee', 'delivery_allowance', 'overage_fee', 'at_limit', 'live_map', 'status', 'notes',
    ];

    protected $attributes = ['status' => 'active', 'at_limit' => 'overage', 'live_map' => false, 'overage_fee' => 0];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'integer',
            'delivery_allowance' => 'integer',
            'overage_fee' => 'integer',
            'at_limit' => LimitPolicy::class,
            'live_map' => 'boolean',
            'status' => BusinessStatus::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DeliveryPlan::class, 'delivery_plan_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BusinessInvoice::class);
    }

    /** Deliveries that count against the month that holds this day: everything not cancelled. */
    public function deliveriesIn(CarbonInterface $month): int
    {
        return $this->deliveries()
            ->whereBetween('delivery_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('status', '!=', DeliveryStatus::Cancelled)
            ->count();
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }
}
