<?php

namespace Modules\Subscriptions\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Payments\Events\MandateApproved;
use Modules\Payments\Events\MandateEvent;
use Modules\Subscriptions\Actions\ChargeAutoRenewal;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Events\AutoRenewalStarted;
use Modules\Subscriptions\Models\AutoRenewal;

/**
 * Hears what became of a direct debit request, and acts on the ones that belong to an automatic renewal.
 * Approved: it is switched on, she is told, and the first charge is taken if one is due.
 * Declined, timed out or cancelled at the gateway: it is switched off.
 */
class FollowMandate implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private ChargeAutoRenewal $charge) {}

    public function handle(MandateEvent $event): void
    {
        if ($event->ownerType !== AutoRenewal::OWNER) {
            return;
        }

        $renewal = AutoRenewal::query()->find($event->ownerId);

        // An answer about a mandate she has since replaced is ignored.
        if (! $renewal || $renewal->mandate_id !== $event->mandateId) {
            return;
        }

        if (! $event instanceof MandateApproved) {
            $renewal->update(['status' => AutoRenewalStatus::Off, 'last_error' => 'The direct debit was not approved.']);

            return;
        }

        if ($renewal->status === AutoRenewalStatus::Pending) {
            $renewal->update(['status' => AutoRenewalStatus::Active]);
            AutoRenewalStarted::dispatch($renewal);
        }

        $this->charge->handle($renewal->fresh());
    }
}
