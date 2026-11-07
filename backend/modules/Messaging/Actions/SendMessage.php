<?php

namespace Modules\Messaging\Actions;

use Modules\Messaging\Channels\ChannelManager;
use Modules\Messaging\Enums\MessageStatus;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageRule;
use Throwable;

/**
 * Tries to deliver one message. It walks the channels in order and stops at the first that works.
 * Every try is written to the message, so the delivery log shows exactly what happened.
 * A message already sent is left alone, so running this twice never sends twice.
 */
class SendMessage
{
    public function __construct(private ChannelManager $channels) {}

    public function handle(Message $message): Message
    {
        if ($message->status === MessageStatus::Sent) {
            return $message;
        }

        // Before the first try, `channel` holds the person's preference. After, the channel last tried.
        $preferred = collect($message->attempts)->isEmpty() ? $message->channel : null;
        $attempts = $message->attempts ?? [];

        foreach ($this->channels->order($message->rule?->channel ?? MessageRule::PREFERRED, $preferred) as $key) {
            $channel = $this->channels->channel($key);

            if ($channel === null) {
                $attempts[] = ['channel' => $key, 'ok' => false, 'error' => 'Not set up', 'at' => now()->toIso8601String()];

                continue;
            }

            try {
                $channel->send($message->recipient_phone, $message->body, $message->recipient_name);
                $attempts[] = ['channel' => $key, 'ok' => true, 'error' => null, 'at' => now()->toIso8601String()];

                $message->update(['status' => MessageStatus::Sent, 'channel' => $key, 'attempts' => $attempts, 'sent_at' => now()]);

                return $message;
            } catch (Throwable $e) {
                $attempts[] = ['channel' => $key, 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200), 'at' => now()->toIso8601String()];
            }
        }

        // The channel last tried, even one that is not set up, so the log never shows a channel nothing went out on.
        $last = end($attempts);
        $message->update(['status' => MessageStatus::Failed, 'channel' => $last ? $last['channel'] : $message->channel, 'attempts' => $attempts]);

        return $message;
    }
}
