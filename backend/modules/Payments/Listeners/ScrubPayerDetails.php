<?php

namespace Modules\Payments\Listeners;

use App\Support\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Payments\Actions\CancelMandate;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentMandate;

/**
 * When someone's data is erased, the payment stays, because the accounts need it, but the phone
 * number and the gateway's raw replies (which repeat it) go. Safe to run twice.
 */
class ScrubPayerDetails implements ShouldQueue
{
    public function __construct(private CancelMandate $cancel) {}

    public function handle(ErasesPersonalData $event): void
    {
        Payment::query()->where('phone', $event->phone())->update(['phone' => null, 'meta' => null]);

        // A mandate on her number is withdrawn first, then stripped the same way.
        PaymentMandate::query()->where('phone', $event->phone())->get()->each(function (PaymentMandate $mandate) {
            $this->cancel->handle($mandate, quietly: true);
            $mandate->update(['phone' => null, 'meta' => null]);
        });
    }
}
