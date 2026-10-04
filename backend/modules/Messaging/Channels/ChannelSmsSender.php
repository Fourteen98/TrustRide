<?php

namespace Modules\Messaging\Channels;

use App\Support\Contracts\SmsSender;
use RuntimeException;

/**
 * Sends a one-off SMS that must always go out, such as a login code, through the same
 * SMS channel the rules use. So when a real provider is plugged in, login codes use it too.
 */
class ChannelSmsSender implements SmsSender
{
    public function __construct(private ChannelManager $channels) {}

    public function send(string $phone, string $message): void
    {
        $sms = $this->channels->channel('sms') ?? throw new RuntimeException('The SMS channel is not set up.');

        $sms->send($phone, $message);
    }
}
