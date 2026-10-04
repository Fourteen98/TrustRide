<?php

namespace Modules\Messaging\Channels;

use InvalidArgumentException;

/** Hands out the sender for a channel, according to config/messaging. Null means "not set up". */
class ChannelManager
{
    public const CHANNELS = ['sms', 'whatsapp', 'push'];

    /** @var array<string, Channel> */
    private array $overrides = [];

    public function channel(string $key): ?Channel
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        $driver = config("messaging.channels.{$key}.driver", 'null');

        return match ($driver) {
            'log' => new LogChannel(strtoupper($key === 'sms' ? 'SMS' : $key)),
            'null', null, '' => null,
            default => throw new InvalidArgumentException("Unknown messaging driver [{$driver}] for the {$key} channel."),
        };
    }

    public function isSetUp(string $key): bool
    {
        return $this->channel($key) !== null;
    }

    /** Swap a channel's sender. Tests use this, and it is how a real provider can be bound at runtime. */
    public function use(string $key, ?Channel $channel): void
    {
        $this->overrides[$key] = $channel;
    }

    /**
     * The channels to try for one person, in order: the rule's fixed channel alone,
     * or her preferred channel first and then the fallbacks.
     *
     * @return list<string>
     */
    public function order(string $ruleChannel, ?string $preferred): array
    {
        if ($ruleChannel !== 'preferred') {
            return [$ruleChannel];
        }

        $first = in_array($preferred, self::CHANNELS, true) ? $preferred : 'sms';

        return array_values(array_unique([$first, ...config('messaging.fallback_order', ['sms'])]));
    }
}
