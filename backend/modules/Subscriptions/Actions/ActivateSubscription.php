<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Events\SubscriptionActivated;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * Starts a subscription and makes the rider's seat active.
 *
 * There is one way to activate, whoever asks. An operator in the console passes himself as $by.
 * A payment callback passes nobody. Both get the same checks and fire the same event.
 * A renewal starts the day after the current subscription ends, so no days are lost.
 */
class ActivateSubscription
{
    public function handle(Rider $rider, Plan $plan, ?Staff $by = null, ?CarbonInterface $startsOn = null): Subscription
    {
        $this->ensureAllowed($rider, $plan);

        return DB::transaction(function () use ($rider, $plan, $by, $startsOn) {
            $seats = $this->seats($rider)->lockForUpdate()->get();
            $this->ensureSeatsFit($rider, $plan, $seats);

            $today = CarbonImmutable::today();
            $current = Subscription::query()
                ->where('rider_id', $rider->id)
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_on', '>=', $today->toDateString())
                ->orderByDesc('ends_on')
                ->first();

            $start = $current
                ? $current->ends_on->addDay()
                : CarbonImmutable::parse($startsOn ?? $today)->startOfDay();

            if ($start->lt($today)) {
                throw new DomainException('A subscription can\'t start in the past.');
            }

            $subscription = Subscription::query()->create([
                'rider_id' => $rider->id,
                'plan_id' => $plan->id,
                'starts_on' => $start,
                'ends_on' => $start->addDays($plan->duration_days - 1),
                'price' => $plan->price,
                'trips_per_day' => $plan->trips_per_day,
                'no_show_policy' => $plan->no_show_policy,
                'notice_cutoff_minutes' => $plan->notice_cutoff_minutes,
                'activated_at' => now(),
                'activated_by' => $by?->id,
            ]);

            Seat::query()->whereKey($seats->modelKeys())->update(['status' => SeatStatus::Active]);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    /**
     * Refuses if this rider can't be given this plan. Called before money is asked for,
     * so nobody pays for something that can't then be activated.
     */
    public function ensureAllowed(Rider $rider, Plan $plan): void
    {
        if (! $plan->is_active) {
            throw new DomainException('That plan is no longer sold.');
        }

        $this->ensureSeatsFit($rider, $plan, $this->seats($rider)->get());
    }

    private function seats(Rider $rider): Builder
    {
        return Seat::query()->where('rider_id', $rider->id)->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active]);
    }

    private function ensureSeatsFit(Rider $rider, Plan $plan, Collection $seats): void
    {
        if ($seats->isEmpty()) {
            throw new DomainException("{$rider->name} has no seat yet. Approve her application first.");
        }

        if ($seats->count() > $plan->trips_per_day) {
            throw new DomainException(
                "The {$plan->name} plan covers {$plan->trips_per_day} trip a day, but she holds {$seats->count()} seats. Choose a plan that covers both, or release a seat."
            );
        }
    }
}
