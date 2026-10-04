<?php

namespace Modules\Payments\Gateways;

use Modules\Payments\Jobs\SettleFakePayment;
use Modules\Payments\Models\Payment;

/**
 * No real money. For development and for trying the app before Hubtel is connected.
 *
 * A payment succeeds by itself a few seconds after it starts, as if the rider had approved the prompt.
 * A phone number ending in 0000 fails instead, so the failure path can be tried.
 * The queue worker must be running for the payment to settle.
 */
class FakeGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'fake';
    }

    public function charge(Payment $payment): ChargeResult
    {
        $seconds = (int) config('payments.fake.settle_after_seconds');

        if ($seconds >= 0) {
            SettleFakePayment::dispatch($payment->id)->delay(now()->addSeconds($seconds));
        }

        return ChargeResult::accepted('FAKE-'.$payment->id, ['note' => 'Fake gateway. No money moved.']);
    }

    public function status(Payment $payment): StatusResult
    {
        return self::wouldSucceed($payment) ? StatusResult::paid('FAKE-'.$payment->id) : StatusResult::unpaid();
    }

    public static function wouldSucceed(Payment $payment): bool
    {
        return ! str_ends_with((string) $payment->phone, '0000');
    }
}
