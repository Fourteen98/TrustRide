<?php

namespace Modules\Deliveries\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business's month: the plan fee, what it used, anything over, and whether it has paid. */
class BusinessInvoice extends Model
{
    protected $fillable = [
        'business_id', 'period', 'plan_fee', 'delivery_allowance', 'deliveries_count', 'overage_count', 'overage_amount',
        'live_map_fee', 'total', 'paid_at', 'paid_by', 'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'period' => DateOnly::class,
            'plan_fee' => 'integer',
            'delivery_allowance' => 'integer',
            'deliveries_count' => 'integer',
            'overage_count' => 'integer',
            'overage_amount' => 'integer',
            'live_map_fee' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
