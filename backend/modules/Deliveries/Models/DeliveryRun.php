<?php

namespace Modules\Deliveries\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Drivers\Models\Driver;

/** One driver's trip through one zone on one day: pick up at each business, then drop each parcel. */
class DeliveryRun extends Model
{
    use HasUlids;

    protected $fillable = ['zone_id', 'date', 'driver_id', 'driver_pay', 'status', 'started_at', 'finished_at', 'created_by'];

    protected $attributes = ['status' => 'planned'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'driver_pay' => 'integer',
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('position');
    }
}
