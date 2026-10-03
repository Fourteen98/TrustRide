<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Network\Models\Stop;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Enums\SeatStatus;

/** One rider's place on one run, at one stop. Reserved when she is approved, active when she has paid. */
class Seat extends Model
{
    protected $fillable = ['rider_id', 'run_id', 'stop_id', 'application_id', 'status', 'released_at'];

    protected $attributes = ['status' => 'reserved'];

    protected function casts(): array
    {
        return ['status' => SeatStatus::class, 'released_at' => 'datetime'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RiderApplication::class, 'application_id');
    }

    public function pickupTime(): ?string
    {
        return $this->run->timeAtStop($this->stop_id);
    }
}
