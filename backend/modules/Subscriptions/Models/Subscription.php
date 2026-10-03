<?php

namespace Modules\Subscriptions\Models;

use App\Support\Casts\DateOnly;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\NoShowPolicy;
use Modules\Subscriptions\Enums\SubscriptionStatus;

class Subscription extends Model
{
    protected $fillable = [
        'rider_id', 'plan_id', 'status', 'starts_on', 'ends_on', 'price', 'trips_per_day', 'no_show_policy',
        'notice_cutoff_minutes', 'credited_days', 'activated_at', 'activated_by', 'expiring_notified_at',
    ];

    protected $attributes = ['status' => 'active', 'credited_days' => 0];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'no_show_policy' => NoShowPolicy::class,
            'starts_on' => DateOnly::class,
            'ends_on' => DateOnly::class,
            'activated_at' => 'datetime',
            'expiring_notified_at' => 'datetime',
            'price' => 'integer',
            'trips_per_day' => 'integer',
            'notice_cutoff_minutes' => 'integer',
            'credited_days' => 'integer',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'activated_by');
    }

    /** Active subscriptions whose dates include the given day. */
    public function scopeCovering(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('status', SubscriptionStatus::Active)
            ->where('starts_on', '<=', $date->toDateString())
            ->where('ends_on', '>=', $date->toDateString());
    }

    public function covers(CarbonInterface $date): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->starts_on->toDateString() <= $date->toDateString()
            && $this->ends_on->toDateString() >= $date->toDateString();
    }
}
