<?php

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Events\PaymentFailed;
use Modules\Payments\Events\PaymentSucceeded;
use Modules\Payments\Models\Payment;

/**
 * The one place a payment becomes paid or failed. The callback, the status check, the fake gateway
 * and an operator settling by hand all end here, so they can't disagree.
 *
 * Safe to call twice, and safe when two of them arrive at the same moment: the row is locked,
 * and the event fires only when the status really changes.
 *
 *   waiting -> paid      fires PaymentSucceeded
 *   waiting -> failed    fires PaymentFailed
 *   failed  -> paid      fires PaymentSucceeded. The money arrived late, and money that arrived counts.
 *   paid    -> anything  nothing. A paid payment is never undone here.
 */
class ApplyOutcome
{
    /** @return bool true if the status changed */
    public function handle(Payment $payment, bool $paid, ?string $transactionId, string $source, mixed $raw = null, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($payment, $paid, $transactionId, $source, $raw, $reason) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($raw !== null) {
                $payment->remember($source, $raw);
            }

            if ($payment->status === PaymentStatus::Success || (! $paid && ! $payment->isWaiting())) {
                return false;
            }

            if ($paid) {
                $payment->update([
                    'status' => PaymentStatus::Success,
                    'paid_at' => now(),
                    'provider_transaction_id' => $transactionId ?: $payment->provider_transaction_id,
                    'failure_reason' => null,
                ]);
                $payment->remember('settled_by', $source);

                PaymentSucceeded::dispatch($payment);
            } else {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => str($reason ?: 'The payment was not approved.')->limit(250)->toString(),
                ]);
                $payment->remember('settled_by', $source);

                PaymentFailed::dispatch($payment);
            }

            return true;
        });
    }
}
