<?php

namespace Modules\Payments\Gateways;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Payments\Models\Payment;
use Modules\Payments\Support\Wire;
use Throwable;

/**
 * Hubtel Direct Receive Money: the rider gets a mobile money prompt on her phone and approves it with her PIN.
 *
 * PROTOCOL
 *
 *  1. charge()  POST {receive_base_url}/merchantaccount/merchants/{merchant}/receive/mobilemoney
 *               Basic auth. Hubtel answers ResponseCode "0001": accepted and pending. Not paid yet.
 *  2. Hubtel POSTs the result to our callback URL. ResponseCode "0000" is paid. Anything else is not.
 *  3. status()  GET {status_base_url}/transactions/{merchant}/status?clientReference=...
 *               For when the callback never arrives. This is the same endpoint chopform uses.
 *
 * WHAT IS CONFIRMED AND WHAT IS NOT
 *
 *  - The status check endpoint, the basic-auth pair, the case-insensitive keys and the IP whitelist
 *    rule are taken from chopform's working Hubtel integration.
 *  - The receive-money endpoint, its field names and its response codes are written from Hubtel's
 *    published shape and have NOT been run against Hubtel from this codebase. The first real
 *    payment must be watched in storage/logs/hubtel-*.log, which records both directions in full.
 *
 * IP WHITELIST. Hubtel only answers the receive-money and status endpoints from whitelisted server IPs.
 * Others get a 403 or a timeout. A local machine cannot use this gateway. Use the fake driver locally.
 */
class HubtelGateway implements PaymentGateway
{
    /** Set when the status API rejects us, so we stop asking for a while. */
    public const STATUS_BLOCKED_KEY = 'payments:hubtel:status_blocked';

    public const STATUS_BLOCKED_MINUTES = 15;

    public function key(): string
    {
        return 'hubtel';
    }

    public function charge(Payment $payment): ChargeResult
    {
        $config = config('payments.hubtel');
        $url = sprintf('%s/merchantaccount/merchants/%s/receive/mobilemoney', rtrim($config['receive_base_url'], '/'), $config['merchant_account_number']);

        $body = [
            'CustomerMsisdn' => Wire::msisdn((string) $payment->phone),
            'Channel' => config("payments.networks.{$payment->network}.channel"),
            'Amount' => round($payment->amount / 100, 2),
            'PrimaryCallbackUrl' => $config['callback_url'] ?: url('/api/webhooks/hubtel/payment'),
            'Description' => $payment->description,
            'ClientReference' => $payment->reference,
        ];

        Log::channel('hubtel')->info('OUT receive-money request', [
            'endpoint' => $url,
            'reference' => $payment->reference,
            'auth_username' => Wire::mask($config['username']),
            'auth_api_key' => Wire::mask($config['api_key']),
            'body' => $body,
        ]);

        try {
            $response = Http::withBasicAuth((string) $config['username'], (string) $config['api_key'])
                ->acceptJson()->asJson()->timeout(20)->post($url, $body);
        } catch (Throwable $e) {
            Log::channel('hubtel')->error('OUT receive-money transport error', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return ChargeResult::refused('Could not reach Hubtel.', $e->getMessage());
        }

        Log::channel('hubtel')->info('IN  receive-money response', [
            'reference' => $payment->reference,
            'http_status' => $response->status(),
            'body' => $response->body(),
        ]);

        $json = $response->json();
        $code = (string) (Wire::pick($json, 'ResponseCode') ?? '');

        // 0001 is "request accepted, the customer has been prompted". 0000 would mean already paid.
        if ($response->successful() && in_array($code, ['0001', '0000'], true)) {
            return ChargeResult::accepted(Wire::pick(Wire::pick($json, 'Data'), 'TransactionId'), $json);
        }

        return ChargeResult::refused(
            (string) (Wire::pick($json, 'Message') ?: "Hubtel refused the request (HTTP {$response->status()})."),
            $json ?: $response->body(),
        );
    }

    public function status(Payment $payment): StatusResult
    {
        // A 401 or 403 means our server IP is not whitelisted. Every further call would fail the same way.
        if (Cache::has(self::STATUS_BLOCKED_KEY)) {
            return StatusResult::unknown('Status checks are paused after a 401 or 403 from Hubtel.');
        }

        $config = config('payments.hubtel');
        $url = sprintf('%s/transactions/%s/status', rtrim($config['status_base_url'], '/'), $config['merchant_account_number']);
        $query = ['clientReference' => $payment->reference];

        Log::channel('hubtel')->info('OUT status-check request', ['reference' => $payment->reference, 'url' => $url]);

        try {
            $response = Http::withBasicAuth((string) $config['username'], (string) $config['api_key'])
                ->acceptJson()->timeout(15)->get($url, $query);
        } catch (Throwable $e) {
            Log::channel('hubtel')->error('OUT status-check transport error', ['reference' => $payment->reference, 'error' => $e->getMessage()]);

            return StatusResult::unknown($e->getMessage());
        }

        Log::channel('hubtel')->info('IN  status-check response', [
            'reference' => $payment->reference,
            'http_status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            Cache::put(self::STATUS_BLOCKED_KEY, now()->toIso8601String(), now()->addMinutes(self::STATUS_BLOCKED_MINUTES));
            Log::channel('hubtel')->warning('Status API rejected us. Pausing status checks.', [
                'http_status' => $response->status(),
                'hint' => 'The server IP must be whitelisted by Hubtel for the Transaction Status Check API.',
            ]);
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            return StatusResult::unknown($json ?: $response->body());
        }

        // Only the status inside "data" counts. The envelope's own "Success" just means the call worked.
        $data = Wire::pick($json, 'data');
        $status = strtolower((string) (Wire::pick($data, 'status') ?? Wire::pick($data, 'transactionStatus') ?? ''));

        return match (true) {
            in_array($status, ['paid', 'success', 'successful', 'completed'], true) => StatusResult::paid(
                Wire::pick($data, 'transactionId') ?? Wire::pick($data, 'externalTransactionId'), $json,
            ),
            in_array($status, ['unpaid', 'pending', 'failed', 'cancelled', 'canceled', 'expired', 'refunded'], true) => StatusResult::unpaid($json),
            default => StatusResult::unknown($json),
        };
    }
}
