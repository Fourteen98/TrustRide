<?php

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventLog extends Model
{
    protected $table = 'event_log';

    public $timestamps = false;

    protected $fillable = ['event_id', 'name', 'payload', 'recipients', 'occurred_at'];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'recipients' => 'array', 'occurred_at' => 'datetime'];
    }
}
