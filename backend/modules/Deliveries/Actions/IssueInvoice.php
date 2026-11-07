<?php

namespace Modules\Deliveries\Actions;

use Carbon\CarbonInterface;
use DomainException;
use Modules\Deliveries\Enums\LimitPolicy;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\BusinessInvoice;

/**
 * A business's bill for a month: its plan fee, each delivery past its allowance at the overage fee, and the
 * live map if it has it. Issuing again before it is paid brings the numbers up to date. Paid outside the system.
 */
class IssueInvoice
{
    public function handle(Business $business, CarbonInterface $month): BusinessInvoice
    {
        $period = $month->copy()->startOfMonth();
        $existing = $business->invoices()->whereDate('period', $period->toDateString())->first();

        if ($existing?->isPaid()) {
            throw new DomainException("{$business->name}'s {$period->format('F Y')} invoice is paid. It can't change.");
        }

        $count = $business->deliveriesIn($period);
        $over = $business->delivery_allowance !== null && $business->at_limit === LimitPolicy::Overage
            ? max(0, $count - $business->delivery_allowance) : 0;
        $overage = $over * $business->overage_fee;
        $map = $business->live_map ? (int) config('deliveries.live_map_fee') : 0;

        $values = [
            'plan_fee' => $business->monthly_fee,
            'delivery_allowance' => $business->delivery_allowance,
            'deliveries_count' => $count,
            'overage_count' => $over,
            'overage_amount' => $overage,
            'live_map_fee' => $map,
            'total' => $business->monthly_fee + $overage + $map,
        ];

        return $existing
            ? tap($existing)->update($values)
            : $business->invoices()->create(['period' => $period->toDateString(), ...$values]);
    }
}
