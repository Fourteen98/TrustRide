<?php

namespace Modules\Network\Models;

use App\Support\Maps\Polyline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Access\Concerns\BelongsToOrganisation;

class Route extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'name', 'origin_area', 'destination', 'is_active', 'path', 'path_traced_at'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'path_traced_at' => 'datetime'];
    }

    /** The stops in travelling order for an outbound run. A return run travels them in reverse. */
    public function stops(): BelongsToMany
    {
        return $this->belongsToMany(Stop::class, 'route_stops')
            ->withPivot(['position', 'minutes_from_start'])
            ->orderBy('route_stops.position');
    }

    /**
     * The road this route follows as [latitude, longitude] points, in outbound order.
     * Null until it has been traced. Use reversed for a return run.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public function roadPath(): ?array
    {
        return $this->path ? Polyline::decode($this->path) : null;
    }

    public function hasStop(int $stopId): bool
    {
        return $this->stops()->where('stops.id', $stopId)->exists();
    }
}
