<?php

namespace Modules\Riders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;

class DataDeletionRequest extends Model
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['rider_id', 'status', 'requested_at', 'completed_at', 'completed_by'];

    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
