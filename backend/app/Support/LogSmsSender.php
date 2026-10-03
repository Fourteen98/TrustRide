<?php

namespace App\Support;

use App\Support\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/** Writes the SMS to the log. Used until the Hubtel sender exists. */
final class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
