<?php

namespace Modules\Drivers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\VettingResult;

class VettingCheck extends Model
{
    protected $fillable = [
        'vetting_item_id', 'driver_id', 'vehicle_id', 'result', 'notes', 'checked_by', 'checked_at',
        'overridden_by', 'override_reason', 'overridden_at',
    ];

    protected function casts(): array
    {
        return ['result' => VettingResult::class, 'checked_at' => 'datetime', 'overridden_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VettingItem::class, 'vetting_item_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'checked_by');
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'overridden_by');
    }

    public function isOverridden(): bool
    {
        return $this->overridden_at !== null;
    }

    /** Passed, or failed but let through by an override. */
    public function isClear(): bool
    {
        return $this->result === VettingResult::Passed || $this->isOverridden();
    }
}
