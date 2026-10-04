<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Models\Payment;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\PurchaseStatus;

class PlanPurchase extends Model
{
    /** What Payments is told this is, so the answer finds its way back here. */
    public const PAYABLE = 'plan_purchase';

    protected $fillable = ['rider_id', 'plan_id', 'amount', 'network', 'status', 'payment_id', 'subscription_id', 'problem'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => PurchaseStatus::class, 'amount' => 'integer'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
