<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\NoShowPolicy;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * A rider says she won't ride on a given day. Whether she gets the day back depends on the
 * no-show rule her subscription was bought with, and on whether she told us in time.
 * A credited day makes the subscription one day longer.
 */
class GiveAbsenceNotice
{
    public function handle(Rider $rider, CarbonInterface $date, ?CarbonInterface $now = null): AbsenceNotice
    {
        return DB::transaction(function () use ($rider, $date, $now) {
            $check = $this->check($rider, $date, $now, lock: true);
            $subscription = $check['subscription'];

            if ($check['credited']) {
                $subscription->update([
                    'ends_on' => $subscription->ends_on->addDay(),
                    'credited_days' => $subscription->credited_days + 1,
                ]);

                // A renewal already bought starts the day after, so it moves one day too.
                Subscription::query()
                    ->where('rider_id', $rider->id)
                    ->where('status', SubscriptionStatus::Active)
                    ->where('starts_on', '>', $check['date']->toDateString())
                    ->whereKeyNot($subscription->id)
                    ->get()
                    ->each(fn (Subscription $later) => $later->update([
                        'starts_on' => $later->starts_on->addDay(),
                        'ends_on' => $later->ends_on->addDay(),
                    ]));
            }

            return AbsenceNotice::query()->create([
                'rider_id' => $rider->id,
                'subscription_id' => $subscription->id,
                'date' => $check['date'],
                'given_at' => $check['now'],
                'in_time' => $check['in_time'],
                'credited' => $check['credited'],
            ]);
        });
    }

    /**
     * What would happen if she gave notice for this day right now. Saves nothing.
     * The app shows this before she confirms, so there are no surprises.
     *
     * @return array{date: CarbonImmutable, in_time: bool, credited: bool, deadline: CarbonImmutable, policy: NoShowPolicy}
     */
    public function preview(Rider $rider, CarbonInterface $date, ?CarbonInterface $now = null): array
    {
        return collect($this->check($rider, $date, $now))->only(['date', 'in_time', 'credited', 'deadline', 'policy'])->all();
    }

    /** Every rule about a notice, in one place. Used by both handle() and preview(). */
    private function check(Rider $rider, CarbonInterface $date, ?CarbonInterface $now, bool $lock = false): array
    {
        $now = CarbonImmutable::parse($now ?? now());
        $date = CarbonImmutable::parse($date)->startOfDay();

        if ($date->lt($now->startOfDay())) {
            throw new DomainException('That day has already passed.');
        }

        $subscription = Subscription::query()
            ->where('rider_id', $rider->id)
            ->covering($date)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        if (! $subscription) {
            throw new DomainException('You have no subscription on that day.');
        }

        if (AbsenceNotice::query()->where('rider_id', $rider->id)->where('date', $date->toDateString())->exists()) {
            throw new DomainException('You have already told us about that day.');
        }

        $firstDeparture = Seat::query()
            ->where('rider_id', $rider->id)
            ->where('status', SeatStatus::Active)
            ->with('run')
            ->get()
            ->filter(fn (Seat $seat) => $seat->run->runsOn($date))
            ->map(fn (Seat $seat) => $seat->run->departureTime())
            ->min();

        if ($firstDeparture === null) {
            throw new DomainException('Your van doesn\'t run that day.');
        }

        $deadline = $date->setTimeFromTimeString($firstDeparture)->subMinutes($subscription->notice_cutoff_minutes);
        $inTime = $now->lte($deadline);

        return [
            'subscription' => $subscription,
            'date' => $date,
            'now' => $now,
            'deadline' => $deadline,
            'in_time' => $inTime,
            'policy' => $subscription->no_show_policy,
            'credited' => match ($subscription->no_show_policy) {
                NoShowPolicy::LoseDay => false,
                NoShowPolicy::CreditWithNotice => $inTime,
                NoShowPolicy::CreditAlways => true,
            },
        ];
    }
}
