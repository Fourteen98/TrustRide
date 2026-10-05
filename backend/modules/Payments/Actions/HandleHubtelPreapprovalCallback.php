<?php

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\Log;
use Modules\Payments\Models\PaymentMandate;
use Modules\Payments\Support\Wire;

/**
 * Reads what Hubtel POSTs after she answers a direct debit request.
 * As with payments, only our own reference is trusted. PreapprovalStatus APPROVED activates the mandate.
 */
class HandleHubtelPreapprovalCallback
{
    public function __construct(private ApplyMandateOutcome $apply) {}

    /** @return string what was done, for the log and the tests */
    public function handle(array $body): string
    {
        Log::channel('hubtel')->info('IN  preapproval callback', ['body' => $body]);

        // Some Hubtel callbacks wrap the fields in Data, some don't. Read either.
        $data = Wire::pick($body, 'Data') ?? $body;
        $reference = (string) (Wire::pick($data, 'ClientReferenceId') ?? Wire::pick($data, 'ClientReference') ?? '');
        $status = strtoupper((string) (Wire::pick($data, 'PreapprovalStatus') ?? ''));

        $mandate = $reference === '' ? null : PaymentMandate::query()->where('reference', $reference)->where('provider', 'hubtel')->first();

        if (! $mandate) {
            Log::channel('hubtel')->warning('Preapproval callback for a reference we do not know. Dropped.', ['reference' => $reference]);

            return 'unknown';
        }

        if ($status === '' || $status === 'PENDING') {
            return 'pending';
        }

        $changed = $this->apply->handle($mandate, $status === 'APPROVED', 'callback', $body, "Hubtel said {$status}.");

        return ! $changed ? 'ignored' : ($status === 'APPROVED' ? 'approved' : 'failed');
    }
}
