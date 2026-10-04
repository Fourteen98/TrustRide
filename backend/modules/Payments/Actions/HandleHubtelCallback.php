<?php

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\Log;
use Modules\Payments\Models\Payment;
use Modules\Payments\Support\Wire;

/**
 * Reads what Hubtel POSTs to us after the rider answers the prompt.
 *
 * Hubtel does not sign its callbacks, so nothing in the body is trusted except the reference.
 * We minted it, it is long and random, and it is never shown to the rider. A body with a
 * reference we don't know is logged and dropped.
 *
 *   ResponseCode 0000   paid
 *   ResponseCode 0001   still pending. Ignored. A later callback or the status check settles it.
 *   anything else       not paid
 */
class HandleHubtelCallback
{
    public function __construct(private ApplyOutcome $apply) {}

    /** @return string what was done, for the log and the tests */
    public function handle(array $body): string
    {
        Log::channel('hubtel')->info('IN  callback', ['body' => $body]);

        $data = Wire::pick($body, 'Data');
        $reference = (string) (Wire::pick($data, 'ClientReference') ?? Wire::pick($body, 'ClientReference') ?? '');
        $code = (string) (Wire::pick($body, 'ResponseCode') ?? '');

        $payment = $reference === '' ? null : Payment::query()->where('reference', $reference)->where('provider', 'hubtel')->first();

        if (! $payment) {
            Log::channel('hubtel')->warning('Callback for a reference we do not know. Dropped.', ['reference' => $reference]);

            return 'unknown';
        }

        if ($code === '0001' || $code === '') {
            return 'pending';
        }

        if ($code !== '0000') {
            $changed = $this->apply->handle($payment, false, null, 'callback', $body, (string) (Wire::pick($body, 'Message') ?: "Hubtel code {$code}"));

            return $changed ? 'failed' : 'ignored';
        }

        // A "paid" for less than we asked is held for a person to look at, not settled.
        $amount = Wire::pick($data, 'Amount');

        if (is_numeric($amount) && (int) round(((float) $amount) * 100) < $payment->amount) {
            $payment->remember('callback_short', $body);
            Log::channel('hubtel')->warning('Callback says paid, but for less than the price. Held.', [
                'reference' => $reference, 'expected_pesewas' => $payment->amount, 'callback_amount' => $amount,
            ]);

            return 'held';
        }

        $changed = $this->apply->handle($payment, true, Wire::pick($data, 'TransactionId'), 'callback', $body);

        return $changed ? 'paid' : 'ignored';
    }
}
