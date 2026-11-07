<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Network\Models\Stop;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Enums\SeatStatus;

/**
 * One rider's place on one run. Reserved when she is approved, active when she has paid.
 *
 * `stop_id` is her own stop, near home. `destination_stop_id` is where she goes, null for the end of the route.
 * Going out she gets on at her stop and off at her destination. Coming back it is the other way round.
 */
class Seat extends Model
{
    protected $fillable = ['rider_id', 'run_id', 'stop_id', 'destination_stop_id', 'application_id', 'status', 'released_at', 'party'];

    protected $attributes = ['status' => 'reserved', 'party' => 1];

    protected function casts(): array
    {
        return ['status' => SeatStatus::class, 'released_at' => 'datetime', 'party' => 'integer'];
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

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    /** Where she goes: the stop she chose, or the end of the route. */
    public function destinationStopId(): int
    {
        return $this->destination_stop_id ?? $this->run->route->stops->last()->id;
    }

    /** Where she gets on this run. */
    public function boardsAt(): int
    {
        return $this->run->direction === Direction::Outbound ? $this->stop_id : $this->destinationStopId();
    }

    /** Where she gets off this run. */
    public function getsOffAt(): int
    {
        return $this->run->direction === Direction::Outbound ? $this->destinationStopId() : $this->stop_id;
    }

    /** When the van reaches the stop she gets on at, "06:38". */
    public function pickupTime(): ?string
    {
        return $this->run->timeAtStop($this->boardsAt());
    }

    /** When the van reaches the stop she gets off at. */
    public function dropTime(): ?string
    {
        return $this->run->timeAtStop($this->getsOffAt());
    }
}
