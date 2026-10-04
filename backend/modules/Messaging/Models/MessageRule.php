<?php

namespace Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** When this event happens, tell these people, on this channel, with these words. */
class MessageRule extends Model
{
    public const PREFERRED = 'preferred';

    public const EVERYONE = 'all';

    protected $fillable = ['name', 'event_name', 'audience', 'channel', 'body', 'is_active'];

    protected $attributes = ['audience' => self::EVERYONE, 'channel' => self::PREFERRED, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function appliesTo(?string $role): bool
    {
        return $this->audience === self::EVERYONE || $this->audience === $role;
    }
}
