<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Access\Models\Staff;
use Modules\Support\Enums\EntryType;

class CaseEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['support_case_id', 'type', 'body', 'staff_id', 'created_at'];

    protected function casts(): array
    {
        return ['type' => EntryType::class, 'created_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
