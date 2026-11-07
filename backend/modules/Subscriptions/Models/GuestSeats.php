<?php

namespace Modules\Subscriptions\Models;

use App\Support\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Riders\Models\Rider;

/** Seats a rider holds on one run on one day for people travelling with her. Each pays the fare to the mate. */
class GuestSeats extends Model
{
    protected $table = 'guest_seats';

    protected $fillable = ['rider_id', 'run_id', 'date', 'seats'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'seats' => 'integer'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
