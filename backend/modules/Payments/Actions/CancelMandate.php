<?php

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\Log;
use Modules\Payments\Enums\MandateStatus;
use Modules\Payments\Events\MandateCancelled;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\PaymentMandate;
use Throwable;

/**
 * Withdraws a mandate. From this moment we never charge it again, whatever the gateway says:
 * our own record is what StartPayment checks. Telling the gateway is done as well, and logged if it fails.
 */
class CancelMandate
{
    public function __construct(private GatewayManager $gateways) {}

    /** @param bool $quietly true when it is being replaced, so the owner is not told it stopped */
    public function handle(PaymentMandate $mandate, bool $quietly = false): void
    {
        if (! in_array($mandate->status, [MandateStatus::Pending, MandateStatus::Active], true)) {
            return;
        }

        $mandate->update(['status' => MandateStatus::Cancelled, 'cancelled_at' => now()]);

        try {
            $agreed = $this->gateways->gateway($mandate->provider)->cancelMandate($mandate);
        } catch (Throwable $e) {
            $agreed = false;
            report($e);
        }

        if (! $agreed) {
            Log::channel('hubtel')->warning('A mandate was cancelled here but the gateway did not confirm it.', ['reference' => $mandate->reference]);
            $mandate->remember('cancel_unconfirmed', now()->toIso8601String());
        }

        if (! $quietly) {
            MandateCancelled::dispatch($mandate);
        }
    }
}
