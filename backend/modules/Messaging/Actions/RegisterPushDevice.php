<?php

namespace Modules\Messaging\Actions;

use Modules\Messaging\Models\PushDevice;

/**
 * Remembers that a phone can take app notifications. The app calls this each time it opens,
 * so a token that changed is picked up and one install never has two rows.
 * A phone that someone else used before is moved to whoever is logged in on it now.
 */
class RegisterPushDevice
{
    public function handle(string $phone, string $token, ?string $platform = null): PushDevice
    {
        return PushDevice::query()->updateOrCreate(
            ['token' => $token],
            ['phone' => $phone, 'platform' => $platform, 'last_seen_at' => now()],
        );
    }

    /** She logged out on that phone, or the push service says the app is gone from it. */
    public function forget(string $token): void
    {
        PushDevice::query()->where('token', $token)->delete();
    }
}
