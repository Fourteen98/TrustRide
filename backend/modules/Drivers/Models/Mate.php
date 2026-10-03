<?php

namespace Modules\Drivers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Concerns\BelongsToOrganisation;
use Modules\Access\Models\Account;

class Mate extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['organisation_id', 'driver_id', 'account_id', 'name', 'phone', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
