<?php

namespace App\Api\Driver;

use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\DriverStatement;

class EarningsController
{
    /**
     * The driver's commission and assessments.
     *
     * What TrustRide pays him each month, and the last twelve months: how he was assessed and whether
     * he has been paid. This is the commission only. Fares never pass through TrustRide.
     */
    public function show(Request $request): JsonResponse
    {
        $driver = Driver::query()->where('account_id', $request->user()->id)->first()
            ?? throw new DomainException('Only a driver has a commission.');

        $statements = DriverStatement::query()->where('driver_id', $driver->id)->orderByDesc('period')->limit(12)->get();

        return response()->json([
            /** What he is paid for a month as things stand, e.g. "GHS 300" */
            'monthly_commission_label' => Money::format($driver->monthly_commission),
            'months' => $statements->map(fn (DriverStatement $statement) => [
                /** e.g. "September 2026" */
                'month' => $statement->period->format('F Y'),
                'commission_label' => Money::format($statement->commission_amount),
                /** A bonus on top, if there was one */
                'bonus_label' => $statement->bonus_amount > 0 ? Money::format($statement->bonus_amount) : null,
                'total_label' => Money::format($statement->total()),
                /** @var 'met'|'not_met'|null Null until the month has been assessed */
                'assessment' => $statement->assessment_outcome?->value,
                /** What the assessor wrote for him */
                'assessment_notes' => $statement->assessment_notes,
                'paid' => $statement->isPaid(),
                'paid_on' => $statement->paid_at?->toDateString(),
            ])->values()->all(),
        ]);
    }
}
