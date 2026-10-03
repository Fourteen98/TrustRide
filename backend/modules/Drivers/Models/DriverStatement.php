<?php

namespace Modules\Drivers\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\AssessmentOutcome;

/** What TrustRide owes one driver for one month, and whether it has been paid. Not fare money. */
class DriverStatement extends Model
{
    protected $fillable = [
        'driver_id', 'period', 'commission_amount', 'bonus_amount', 'assessment_outcome', 'assessment_notes',
        'assessed_by', 'assessed_at', 'paid_at', 'paid_by', 'payment_reference',
    ];

    protected $attributes = ['bonus_amount' => 0];

    protected function casts(): array
    {
        return [
            'period' => DateOnly::class,
            'assessment_outcome' => AssessmentOutcome::class,
            'assessed_at' => 'datetime',
            'paid_at' => 'datetime',
            'commission_amount' => 'integer',
            'bonus_amount' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assessed_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'paid_by');
    }

    /** In pesewas. */
    public function total(): int
    {
        return $this->commission_amount + $this->bonus_amount;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
