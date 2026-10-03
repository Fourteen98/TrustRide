<?php

namespace Modules\Drivers\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\DriverStatus;

class Driver extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id', 'account_id', 'name', 'phone', 'licence_number', 'licence_expires_on', 'ghana_card_number',
        'next_of_kin_name', 'next_of_kin_phone', 'monthly_commission', 'status', 'status_note', 'approved_at', 'approved_by',
    ];

    protected $attributes = ['status' => 'pending', 'monthly_commission' => 0];

    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'licence_expires_on' => DateOnly::class,
            'approved_at' => 'datetime',
            'monthly_commission' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function mates(): HasMany
    {
        return $this->hasMany(Mate::class);
    }

    public function vettingChecks(): HasMany
    {
        return $this->hasMany(VettingCheck::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(DriverStatement::class);
    }

    public function isApproved(): bool
    {
        return $this->status === DriverStatus::Approved;
    }
}
