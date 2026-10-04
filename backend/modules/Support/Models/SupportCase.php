<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\CaseCategory;
use Modules\Support\Enums\CaseStatus;
use Modules\Support\Enums\EntryType;
use Modules\Support\Enums\Severity;

class SupportCase extends Model
{
    protected $fillable = [
        'account_id', 'name', 'phone', 'role', 'source', 'category', 'context', 'status', 'assigned_to',
        'is_incident', 'severity', 'resolution', 'resolved_at', 'resolved_by', 'last_message_at',
    ];

    protected $attributes = ['status' => 'open', 'source' => 'app', 'is_incident' => false];

    protected function casts(): array
    {
        return [
            'category' => CaseCategory::class,
            'status' => CaseStatus::class,
            'severity' => Severity::class,
            'is_incident' => 'boolean',
            'resolved_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'resolved_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CaseEntry::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === CaseStatus::Open;
    }

    /** Adds a line to the timeline. The only way one is written. */
    public function log(EntryType $type, ?string $body = null, ?Staff $by = null): CaseEntry
    {
        return $this->entries()->create(['type' => $type, 'body' => $body, 'staff_id' => $by?->id, 'created_at' => now()]);
    }
}
