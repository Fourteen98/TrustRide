<?php

namespace Modules\Messaging\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Models\Message;

/** Delivers one message on the queue, so a slow provider never slows the person who caused it. */
class DeliverMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(SendMessage $send): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message) {
            $send->handle($message);
        }
    }
}
