<?php

namespace Modules\Payments\Gateways;

use Modules\Payments\Jobs\SettleFakeMandate;
use Modules\Payments\Jobs\SettleFakePayment;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentMandate;

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

    public static function wouldSucceed(Payment|PaymentMandate $subject): bool
    {
        return ! str_ends_with((string) $subject->phone, '0000');
    }

    public function requestMandate(PaymentMandate $mandate): MandateResult
    {
        // A number ending 1111 is confirmed with a code, like the networks that text one. The code is 1234.
        if (str_ends_with((string) $mandate->phone, '1111')) {
            return MandateResult::accepted('FAKE-M-'.$mandate->id, 'otp', 'TEST', ['note' => 'Fake gateway. The code is 1234.']);
        }

        $this->settleMandateLater($mandate);

        return MandateResult::accepted('FAKE-M-'.$mandate->id, 'ussd', null, ['note' => 'Fake gateway. Approves itself.']);
    }

    public function confirmMandate(PaymentMandate $mandate, string $code): MandateResult
    {
        if (! in_array($code, ['1234', 'TEST-1234'], true)) {
            return MandateResult::refused('That code is not right.');
        }

        $this->settleMandateLater($mandate, 0);

        return MandateResult::accepted($mandate->provider_mandate_id);
    }

    public function cancelMandate(PaymentMandate $mandate): bool
    {
        return true;
    }

    private function settleMandateLater(PaymentMandate $mandate, ?int $seconds = null): void
    {
        $seconds ??= (int) config('payments.fake.settle_after_seconds');

        if ($seconds >= 0) {
            SettleFakeMandate::dispatch($mandate->id)->delay(now()->addSeconds($seconds));
        }
    }
}
