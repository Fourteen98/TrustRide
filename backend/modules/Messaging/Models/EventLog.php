<?php

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'event_log';

    public $timestamps = false;

    protected $fillable = ['event_id', 'name', 'payload', 'recipients', 'occurred_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'recipients' => 'array', 'occurred_at' => 'datetime'];
    }
}
