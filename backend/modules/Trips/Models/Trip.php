<?php

namespace Modules\Trips\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Modules\Network\Models\Stop;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Models\Run;
use Modules\Trips\Enums\TripStatus;

class Trip extends Model
{
    use HasUlids;

    protected $fillable = [
        'run_id', 'date', 'status', 'started_at', 'ended_at', 'started_by_account', 'ended_by_account',
        'started_by_staff', 'ended_by_staff', 'reached_stop_id', 'latitude', 'longitude', 'located_at',
    ];

    protected $attributes = ['status' => 'running'];

    /** Only the share-link id is a ULID. The primary key stays an integer. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'date' => DateOnly::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'located_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function reachedStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'reached_stop_id');
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(TripPickup::class);
    }

    public function pings(): HasMany
    {
        return $this->hasMany(LocationPing::class);
    }

    public function isRunning(): bool
    {
        return $this->status === TripStatus::Running;
    }

    /**
     * The route's stops in the order this trip reaches them. A return run travels the route backwards.
     *
     * @return Collection<int, Stop>
     */
    public function stopsInOrder(): Collection
    {
        $stops = $this->run->route->stops;

        return ($this->run->direction === Direction::Return ? $stops->reverse() : $stops)->values();
    }

    /** Where a stop comes on this trip, counting from 0. Null if the trip doesn't call there. */
    public function orderOf(?int $stopId): ?int
    {
        $index = $this->stopsInOrder()->search(fn (Stop $stop) => $stop->id === $stopId);

        return $index === false ? null : $index;
    }

    /** True if the position is recent enough to call live. */
    public function hasFreshPosition(): bool
    {
        return $this->located_at !== null && $this->located_at->gt(now()->subSeconds((int) config('trips.fresh_for_seconds')));
    }
}
