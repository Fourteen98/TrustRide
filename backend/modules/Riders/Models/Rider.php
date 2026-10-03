<?php

namespace Modules\Riders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Models\Account;
use Modules\Riders\Enums\RiderStatus;

class Rider extends Model
{
    protected $fillable = ['account_id', 'name', 'status'];

    protected $attributes = ['status' => 'applicant'];

    protected function casts(): array
    {
        return ['status' => RiderStatus::class];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RiderApplication::class);
    }
}
