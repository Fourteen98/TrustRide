<?php

namespace Modules\Subscriptions\Actions;

use Modules\Payments\Actions\CancelMandate;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Events\AutoRenewalStopped;
use Modules\Subscriptions\Models\AutoRenewal;

/**
 * Turns automatic renewal off and withdraws the direct debit. Her running subscription is untouched.
 * She can do this at any time, and so can an operator. $because is set when we do it ourselves
 * after failed charges, and then she is told.
 */
class StopAutoRenewal
{
    public function __construct(private CancelMandate $cancel) {}

    public function handle(AutoRenewal $renewal, ?string $because = null): AutoRenewal
    {
        if ($renewal->status === AutoRenewalStatus::Off) {
            return $renewal;
        }

        $renewal->update(['status' => AutoRenewalStatus::Off, 'last_error' => $because ?? $renewal->last_error]);

        if ($renewal->mandate) {
            $this->cancel->handle($renewal->mandate, quietly: true);
        }

        if ($because !== null) {
            AutoRenewalStopped::dispatch($renewal);
        }

        return $renewal;
    }
}
