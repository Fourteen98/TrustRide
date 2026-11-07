<?php

namespace Modules\Drivers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Drivers\Enums\ExtraSeats;
use Modules\Drivers\Enums\VehicleOwnership;
use Modules\Drivers\Enums\VehicleStatus;

class Vehicle extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'driver_id', 'registration_number', 'make', 'colour', 'seats_total', 'status', 'condition_note', 'ownership', 'owner_name', 'extra_seats'];

    protected $attributes = ['status' => 'pending', 'ownership' => 'own', 'extra_seats' => 'off'];

    protected function casts(): array
    {
        return ['status' => VehicleStatus::class, 'seats_total' => 'integer', 'ownership' => VehicleOwnership::class, 'extra_seats' => ExtraSeats::class];
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
