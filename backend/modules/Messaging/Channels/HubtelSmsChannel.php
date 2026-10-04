<?php

namespace Modules\Messaging\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real SMS through Hubtel.
 *
 *   POST https://sms.hubtel.com/v1/messages/send   {from, to, content}
 *   Basic auth with the SMS key pair (client id : client secret).
 *
 * Hubtel answers 2xx with a messageId when it has taken the message. That is "accepted", not "delivered".
 * Anything else throws, so the delivery log shows the failure and the next channel is tried.
 * Both directions are written to storage/logs/hubtel-*.log, without the message text or the secret.
 */
class HubtelSmsChannel implements Channel
{
    public function send(string $phone, string $body, ?string $name = null): void
    {
        $config = config('messaging.hubtel_sms');

        if (! $config['client_id'] || ! $config['client_secret']) {
            throw new RuntimeException('Hubtel SMS keys are not set. Add HUBTEL_SMS_CLIENT_ID and HUBTEL_SMS_CLIENT_SECRET to .env.');
        }

        $response = Http::withBasicAuth($config['client_id'], $config['client_secret'])
            ->asJson()->acceptJson()->timeout(20)
            ->post($config['endpoint'], [
                'from' => $config['sender_id'],
                'to' => ltrim($phone, '+'),
                'content' => $body,
            ]);

        $json = $response->json() ?? [];

        Log::channel('hubtel')->info('SMS send', [
            'to' => $phone,
            'from' => $config['sender_id'],
            'length' => mb_strlen($body),
            'http_status' => $response->status(),
            'response' => $json ?: $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Hubtel SMS error: '.($json['message'] ?? $json['Message'] ?? "HTTP {$response->status()}"));
        }
    }
}
