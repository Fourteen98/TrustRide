<?php

namespace Modules\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Payments\Actions\ApplyMandateOutcome;
use Modules\Payments\Gateways\FakeGateway;
use Modules\Payments\Models\PaymentMandate;

/** The fake gateway's stand-in for her approving the direct debit on her phone. */
class SettleFakeMandate implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mandateId) {}

    public function handle(ApplyMandateOutcome $apply): void
    {
        $mandate = PaymentMandate::query()->find($this->mandateId);

        if ($mandate && $mandate->provider === 'fake') {
            $apply->handle($mandate, FakeGateway::wouldSucceed($mandate), 'fake', null, 'Declined on the phone.');
        }
    }
}
