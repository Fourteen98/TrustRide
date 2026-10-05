<?php

namespace Modules\Trips\Models;

use Illuminate\Database\Eloquent\Model;

class LocationPing extends Model
{
    public $timestamps = false;

    protected $fillable = ['trip_id', 'account_id', 'latitude', 'longitude', 'accuracy', 'recorded_at', 'created_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'recorded_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
