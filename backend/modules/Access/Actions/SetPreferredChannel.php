<?php

namespace Modules\Access\Actions;

use DomainException;
use Modules\Access\Models\Account;

/**
 * How someone would like to be reached: in the app, or by SMS. If the one she picks fails,
 * delivery still falls back to the others, so a message is not lost to a dead battery.
 * Login codes always go by SMS, whatever is chosen here.
 */
class SetPreferredChannel
{
    public const CHANNELS = ['push', 'sms', 'whatsapp'];

    public function handle(Account $account, string $channel): Account
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new DomainException('Choose how you want to be reached.');
        }

        $account->update(['preferred_channel' => $channel]);

        return $account;
    }
}
