<?php

namespace Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Access\Concerns\BelongsToOrganisation;

class Route extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'name', 'origin_area', 'destination', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The stops in travelling order for an outbound run. A return run travels them in reverse. */
    public function stops(): BelongsToMany
    {
        return $this->belongsToMany(Stop::class, 'route_stops')
            ->withPivot(['position', 'minutes_from_start'])
            ->orderBy('route_stops.position');
    }

    public function hasStop(int $stopId): bool
    {
        return $this->stops()->where('stops.id', $stopId)->exists();
    }
}
