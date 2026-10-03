<?php

namespace Modules\Subscriptions\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Network\Models\Route;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Enums\SeatStatus;

/** One driver, one vehicle, one route, one direction, one departure time, on set days of the week. */
class Run extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id', 'route_id', 'driver_id', 'vehicle_id', 'direction', 'departs_at', 'days_of_week', 'seats_held', 'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'days_of_week' => 'array',
            'is_active' => 'boolean',
            'seats_held' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /** Seats that count against capacity: reserved or active. */
    public function takenSeats(): HasMany
    {
        return $this->seats()->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active]);
    }

    public function seatsFree(): int
    {
        return max(0, $this->seats_held - $this->takenSeats()->count());
    }

    public function runsOn(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), array_map('intval', $this->days_of_week ?? []), true);
    }

    /** "06:30" */
    public function departureTime(): string
    {
        return substr((string) $this->departs_at, 0, 5);
    }

    /**
     * When the van reaches a stop. Outbound, that is the stop's minutes from the first stop.
     * On the way back the route is travelled in reverse, so it is the minutes from the last stop.
     */
    public function timeAtStop(int $stopId): ?string
    {
        $stops = $this->route->stops;
        $stop = $stops->firstWhere('id', $stopId);

        if (! $stop) {
            return null;
        }

        $offset = $this->direction === Direction::Outbound
            ? $stop->pivot->minutes_from_start
            : $stops->max('pivot.minutes_from_start') - $stop->pivot->minutes_from_start;

        return Carbon::parse($this->departureTime())->addMinutes($offset)->format('H:i');
    }

    public function label(): string
    {
        return "{$this->route->name}, {$this->departureTime()}, {$this->driver->name}";
    }
}
