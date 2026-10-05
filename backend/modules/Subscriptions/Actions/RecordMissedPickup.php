<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Subscription;

/**
 * The van came and the mate did not find her. What that does to her subscription is set on the plan
 * she bought: nothing (the default, she is only told), or the day is added back.
 *
 * undo() takes it back, for when the mate tapped the wrong button and then corrected it.
 * Both are safe to call twice.
 */
class RecordMissedPickup
{
    public const SOURCE = 'missed_pickup';

    public function __construct(private CreditDays $credit) {}

    public function handle(int $riderId, CarbonInterface $date): ?AbsenceNotice
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return DB::transaction(function () use ($riderId, $date) {
            $subscription = Subscription::query()->where('rider_id', $riderId)->covering($date)->lockForUpdate()->first();

            if (! $subscription || ! $subscription->credit_missed_pickup) {
                return null;
            }

            // One line per rider per day. If she already told us, or this already ran, there is nothing more to give.
            if (AbsenceNotice::query()->where('rider_id', $riderId)->where('date', $date->toDateString())->exists()) {
                return null;
            }

            $this->credit->handle($subscription, 1, $date);

            return AbsenceNotice::query()->create([
                'rider_id' => $riderId,
                'subscription_id' => $subscription->id,
                'date' => $date,
                'given_at' => now(),
                'in_time' => false,
                'credited' => true,
                'source' => self::SOURCE,
            ]);
        });
    }

    public function undo(int $riderId, CarbonInterface $date): void
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        DB::transaction(function () use ($riderId, $date) {
            $notice = AbsenceNotice::query()
                ->where('rider_id', $riderId)->where('date', $date->toDateString())->where('source', self::SOURCE)
                ->lockForUpdate()->first();

            if (! $notice) {
                return;
            }

            if ($notice->credited && ($subscription = Subscription::query()->lockForUpdate()->find($notice->subscription_id))) {
                $this->credit->handle($subscription, -1, $date);
            }

            $notice->delete();
        });
    }
}
