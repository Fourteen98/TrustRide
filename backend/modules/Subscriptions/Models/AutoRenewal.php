<?php

namespace Modules\Subscriptions\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Models\PaymentMandate;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\AutoRenewalStatus;

class AutoRenewal extends Model
{
    /** What Payments is told owns the mandate, so the answer finds its way back here. */
    public const OWNER = 'auto_renewal';

    protected $fillable = ['rider_id', 'plan_id', 'mandate_id', 'status', 'failures', 'last_error', 'last_attempt_on', 'seats'];

    protected $attributes = ['status' => 'pending', 'failures' => 0];

    protected function casts(): array
    {
        return ['status' => AutoRenewalStatus::class, 'failures' => 'integer', 'last_attempt_on' => DateOnly::class];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(PaymentMandate::class, 'mandate_id');
    }

    public function isActive(): bool
    {
        return $this->status === AutoRenewalStatus::Active;
    }
}
