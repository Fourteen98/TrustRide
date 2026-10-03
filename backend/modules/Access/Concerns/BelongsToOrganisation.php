<?php

namespace Modules\Access\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Organisation;

/**
 * For anything a partner company could own one day: drivers, vehicles, routes, runs.
 * Until partners exist, everything belongs to TrustRide, organisation 1.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::creating(function ($model) {
            $model->organisation_id ??= Organisation::platformOwner()->id;
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
