<?php

namespace Modules\Messaging\Channels;

/**
 * One way of reaching a phone. To add a real provider, implement this and register it in ChannelManager.
 * send() returns normally when the provider accepted the message, and throws when it didn't.
 */
interface Channel
{
    public function send(string $phone, string $body, ?string $name = null): void;
}
