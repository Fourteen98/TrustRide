<?php

namespace Modules\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Stop extends Model
{
    protected $fillable = ['name', 'landmark', 'area', 'latitude', 'longitude', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_stops')->withPivot(['position', 'minutes_from_start']);
    }
}
