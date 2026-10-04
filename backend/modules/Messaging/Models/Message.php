<?php

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Messaging\Enums\MessageStatus;

/** One message to one person, and what happened to it. */
class Message extends Model
{
    protected $fillable = [
        'event_log_id', 'message_rule_id', 'recipient_phone', 'recipient_name', 'recipient_role',
        'channel', 'body', 'status', 'attempts', 'sent_at',
    ];

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return ['status' => MessageStatus::class, 'attempts' => 'array', 'sent_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventLog::class, 'event_log_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MessageRule::class, 'message_rule_id');
    }

    /** Why it failed, in a few words, from the last channel tried. */
    public function lastError(): ?string
    {
        return collect($this->attempts)->where('ok', false)->last()['error'] ?? null;
    }
}
