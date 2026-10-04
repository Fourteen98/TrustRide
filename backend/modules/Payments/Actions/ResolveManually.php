<?php

namespace Modules\Payments\Actions;

use DomainException;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;

/**
 * An operator settles a payment by hand, after checking the money on the Hubtel dashboard.
 * For when the callback and the status check both failed us. The note and the name are kept on the payment.
 */
class ResolveManually
{
    public function __construct(private ApplyOutcome $apply) {}

    public function handle(Payment $payment, bool $paid, string $by, string $note): void
    {
        if ($payment->status === PaymentStatus::Success) {
            throw new DomainException('That payment is already paid.');
        }

        if (! $paid && ! $payment->isWaiting()) {
            throw new DomainException('That payment has already failed.');
        }

        if (trim($note) === '') {
            throw new DomainException('Say how you checked.');
        }

        $this->apply->handle($payment, $paid, null, 'manual', ['by' => $by, 'note' => $note, 'at' => now()->toIso8601String()], "Marked failed by {$by}: {$note}");
    }
}
