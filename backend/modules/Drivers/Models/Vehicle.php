<?php

namespace Modules\Drivers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Drivers\Enums\VehicleStatus;

class Vehicle extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'driver_id', 'registration_number', 'make', 'colour', 'seats_total', 'status', 'condition_note'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => VehicleStatus::class, 'seats_total' => 'integer'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vettingChecks(): HasMany
    {
        return $this->hasMany(VettingCheck::class);
    }

    public function isApproved(): bool
    {
        return $this->status === VehicleStatus::Approved;
    }
}
