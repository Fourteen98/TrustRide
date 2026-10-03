<?php

namespace App\Support\Contracts;

/**
 * Sends one SMS that must always go out, such as a login code.
 * Rule-driven messages go through Messaging instead.
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
