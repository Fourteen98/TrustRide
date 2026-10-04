<?php

namespace Modules\Payments\Console;

use Illuminate\Console\Command;
use Modules\Payments\Actions\ReconcilePayment;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Models\Payment;

class ReconcileCommand extends Command
{
    protected $signature = 'payments:reconcile {--reference= : Check one payment by its reference}';

    protected $description = 'Ask the gateway about payments still waiting, for when a callback never came';

    public function handle(ReconcilePayment $reconcile): int
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

        $this->info($payments->isEmpty()
            ? 'Nothing waiting.'
            : 'Checked '.$payments->count().': '.collect($counts)->map(fn ($n, $k) => "{$n} {$k}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
