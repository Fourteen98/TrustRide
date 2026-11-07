<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Network\Models\Stop;
use Modules\Riders\Models\Rider;
use Modules\Trips\Enums\PickupStatus;

class TripPickup extends Model
{
    protected $fillable = ['trip_id', 'rider_id', 'stop_id', 'status', 'marked_by_account', 'marked_at', 'alighted_at', 'alighted_stop_id'];

    protected function casts(): array
    {
        return ['status' => PickupStatus::class, 'marked_at' => 'datetime', 'alighted_at' => 'datetime'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function alightedStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'alighted_stop_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }
}
