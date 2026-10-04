<?php

namespace Modules\Payments\Listeners;

use App\Support\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Payments\Models\Payment;

/**
 * When someone's data is erased, the payment stays, because the accounts need it, but the phone
 * number and the gateway's raw replies (which repeat it) go. Safe to run twice.
 */
class ScrubPayerDetails implements ShouldQueue
{
    public function handle(ErasesPersonalData $event): void
    {
        Payment::query()->where('phone', $event->phone())->update(['phone' => null, 'meta' => null]);
    }
}
