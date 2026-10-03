<?php

namespace Modules\Riders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RejectionReason;

class RiderApplication extends Model
{
    protected $fillable = [
        'rider_id', 'route_id', 'stop_id', 'morning_time', 'evening_time', 'status',
        'rejection_reason', 'rejection_note', 'reviewed_by', 'reviewed_at',
    ];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'rejection_reason' => RejectionReason::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }
}
