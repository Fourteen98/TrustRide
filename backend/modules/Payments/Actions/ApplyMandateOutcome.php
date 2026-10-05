<?php

namespace Modules\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payments\Enums\MandateStatus;
use Modules\Payments\Events\MandateApproved;
use Modules\Payments\Events\MandateFailed;
use Modules\Payments\Models\PaymentMandate;

/**
 * The one place a mandate becomes active or failed. Safe to call twice, and under a lock,
 * the same way ApplyOutcome settles a payment. Only a mandate that is still waiting changes.
 */
class ApplyMandateOutcome
{
    /** @return bool true if the status changed */
    public function handle(PaymentMandate $mandate, bool $approved, string $source, mixed $raw = null, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($mandate, $approved, $source, $raw, $reason) {
            $mandate = PaymentMandate::query()->lockForUpdate()->findOrFail($mandate->id);

            if ($raw !== null) {
                $mandate->remember($source, $raw);
            }

            if (! $mandate->isPending()) {
                return false;
            }

            if ($approved) {
                $mandate->update(['status' => MandateStatus::Active, 'approved_at' => now(), 'failure_reason' => null]);
                MandateApproved::dispatch($mandate);
            } else {
                $mandate->update(['status' => MandateStatus::Failed, 'failure_reason' => str($reason ?: 'Not approved.')->limit(250)->toString()]);
                MandateFailed::dispatch($mandate);
            }

            return true;
        });
    }
}
