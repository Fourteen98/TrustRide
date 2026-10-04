<?php

namespace Modules\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Gateways\FakeGateway;
use Modules\Payments\Models\Payment;

/** The fake gateway's stand-in for a rider approving the prompt on her phone. */
class SettleFakePayment implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $paymentId) {}

    public function handle(ApplyOutcome $apply): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment && $payment->provider === 'fake') {
            $apply->handle($payment, FakeGateway::wouldSucceed($payment), 'FAKE-'.$payment->id, 'fake', ['settled_at' => now()->toIso8601String()]);
        }
    }
}
