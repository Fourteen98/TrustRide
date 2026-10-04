<?php

namespace Modules\Messaging\Actions;

use Modules\Messaging\Jobs\DeliverMessage;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\Message;
use Modules\Messaging\Models\MessageRule;

/**
 * Turns one logged event into messages: for every active rule on that event, one message
 * per matching recipient. Safe to run twice, because a message is unique per event, rule and person.
 */
class ApplyRules
{
    public function __construct(private RenderTemplate $render) {}

    /** @return int How many new messages were queued */
    public function handle(EventLog $event): int
    {
        $queued = 0;

        $rules = MessageRule::query()->where('event_name', $event->name)->where('is_active', true)->get();

        foreach ($rules as $rule) {
            foreach ($event->recipients ?? [] as $recipient) {
                if (empty($recipient['phone']) || ! $rule->appliesTo($recipient['role'] ?? null)) {
                    continue;
                }

                $message = Message::query()->firstOrCreate(
                    [
                        'event_log_id' => $event->id,
                        'message_rule_id' => $rule->id,
                        'recipient_phone' => $recipient['phone'],
                    ],
                    [
                        'recipient_name' => $recipient['name'] ?? null,
                        'recipient_role' => $recipient['role'] ?? null,
                        // Until it is sent, this holds the channel she prefers. Delivery reads it.
                        'channel' => $recipient['channel'] ?? null,
                        'body' => $this->render->handle($rule->body, [...$event->payload, 'name' => $recipient['name'] ?? '']),
                    ],
                );

                if ($message->wasRecentlyCreated) {
                    DeliverMessage::dispatch($message->id);
                    $queued++;
                }
            }
        }

        return $queued;
    }
}
