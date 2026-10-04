<?php

namespace Modules\Payments\Actions;

use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\Payment;

/**
 * Asks the gateway what happened to a payment whose callback never came.
 *
 * "The gateway couldn't say" never fails a payment. Only a clear "not paid", after the rider
 * has had her time to approve, does.
 */
class ReconcilePayment
{
    public function __construct(private GatewayManager $gateways, private ApplyOutcome $apply) {}

    /** @return string paid, failed, waiting, unknown or settled */
    public function handle(Payment $payment): string
    {
        if (! $payment->isWaiting()) {
            return 'settled';
        }

        $result = $this->gateways->gateway($payment->provider)->status($payment);

        if (! $result->known) {
            return 'unknown';
        }

        if ($result->paid) {
            $this->apply->handle($payment, true, $result->transactionId, 'status_check', $result->raw);

            return 'paid';
        }

        if ($payment->created_at->gt(now()->subMinutes((int) config('payments.fail_after_minutes')))) {
            return 'waiting';
        }

        $this->apply->handle($payment, false, null, 'status_check', $result->raw, 'The prompt was not approved in time.');

        return 'failed';
    }
}
