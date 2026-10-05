<?php

namespace Modules\Payments\Console;

use Illuminate\Console\Command;
use Modules\Payments\Actions\ApplyMandateOutcome;
use Modules\Payments\Actions\ReconcilePayment;
use Modules\Payments\Enums\MandateStatus;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Gateways\GatewayManager;
use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentMandate;

class ReconcileCommand extends Command
{
    protected $signature = 'payments:reconcile {--reference= : Check one payment by its reference}';

    protected $description = 'Ask the gateway about payments still waiting, for when a callback never came';

    public function handle(ReconcilePayment $reconcile, ApplyMandateOutcome $applyMandate, GatewayManager $gateways): int
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::Initiated)
            ->when(
                $this->option('reference'),
                fn ($query, $reference) => $query->where('reference', $reference),
                // Give the callback two minutes to arrive by itself first.
                fn ($query) => $query->where('created_at', '<=', now()->subMinutes(2)),
            )
            ->oldest('id')
            ->limit(100)
            ->get();

        $counts = [];

        foreach ($payments as $payment) {
            $outcome = $reconcile->handle($payment);
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        }

        // A direct debit request nobody answered is closed, and withdrawn at the gateway in case it was approved late.
        if (! $this->option('reference')) {
            PaymentMandate::query()
                ->where('status', MandateStatus::Pending)
                ->where('created_at', '<=', now()->subMinutes((int) config('payments.fail_after_minutes')))
                ->get()
                ->each(function (PaymentMandate $mandate) use ($applyMandate, $gateways) {
                    if ($applyMandate->handle($mandate, false, 'expired', null, 'Not approved in time.')) {
                        rescue(fn () => $gateways->gateway($mandate->provider)->cancelMandate($mandate), report: false);
                    }
                });
        }

        $this->info($payments->isEmpty()
            ? 'Nothing waiting.'
            : 'Checked '.$payments->count().': '.collect($counts)->map(fn ($n, $k) => "{$n} {$k}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
