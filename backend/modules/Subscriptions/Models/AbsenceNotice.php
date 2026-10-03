<?php

namespace Modules\Subscriptions\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Riders\Models\Rider;

class AbsenceNotice extends Model
{
    protected $fillable = ['rider_id', 'subscription_id', 'date', 'given_at', 'in_time', 'credited'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'given_at' => 'datetime', 'in_time' => 'boolean', 'credited' => 'boolean'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
