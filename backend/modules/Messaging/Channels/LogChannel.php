<?php

namespace Modules\Messaging\Channels;

use Illuminate\Support\Facades\Log;

/** Writes the message to the log instead of sending it. For development, and until a provider is chosen. */
class LogChannel implements Channel
{
    public function __construct(private string $label) {}

    public function send(string $phone, string $body, ?string $name = null): void
    {
        Log::info("{$this->label} to {$phone}: {$body}");
    }
}
