<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\ExtraSeats;
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
 *
 * On vans where people travelling with her are added to her plan, $seats is how many it is bought for, her included.
 * Her seat then holds that many, and the price is the plan's times that. Left out, she keeps what her seat holds now.
 */
class ActivateSubscription
{
    public function handle(Rider $rider, Plan $plan, ?Staff $by = null, ?CarbonInterface $startsOn = null, ?int $seats = null): Subscription
    {
        $this->ensureAllowed($rider, $plan, $seats);

        return DB::transaction(function () use ($rider, $plan, $by, $startsOn, $seats) {
            $held = $this->seats($rider)->with('run.vehicle')->lockForUpdate()->get();
            $this->ensureSeatsFit($rider, $plan, $held);
            $party = $seats ?? (int) $held->max('party');
            $this->ensurePartyFits($held, $party);

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
                'price' => $plan->price * $party,
                'seats' => $party,
                'trips_per_day' => $plan->trips_per_day,
                'no_show_policy' => $plan->no_show_policy,
                'notice_cutoff_minutes' => $plan->notice_cutoff_minutes,
                'credit_missed_pickup' => $plan->credit_missed_pickup,
                'activated_at' => now(),
                'activated_by' => $by?->id,
            ]);

            Seat::query()->whereKey($held->modelKeys())->update(['status' => SeatStatus::Active, 'party' => $party]);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    /**
     * Refuses if this rider can't be given this plan. Called before money is asked for,
     * so nobody pays for something that can't then be activated.
     */
    public function ensureAllowed(Rider $rider, Plan $plan, ?int $seats = null): void
    {
        if (! $plan->is_active) {
            throw new DomainException('That plan is no longer sold.');
        }

        $held = $this->seats($rider)->with('run.vehicle')->get();
        $this->ensureSeatsFit($rider, $plan, $held);

        if ($seats !== null) {
            $this->ensurePartyFits($held, $seats);
        }
    }

    /**
     * People on her plan are only for vans that allow it, up to the most allowed, and only while the run has the seats.
     * Her own seat is already counted, so only the change needs room.
     *
     * @param  Collection<int, Seat>  $held
     */
    private function ensurePartyFits(Collection $held, int $party): void
    {
        $max = 1 + (int) config('subscriptions.extra_seats_max');

        if ($party < 1 || $party > $max) {
            throw new DomainException("A plan can be for 1 to {$max} people.");
        }

        if ($party === 1) {
            return;
        }

        foreach ($held as $seat) {
            if ($seat->run->vehicle->extra_seats !== ExtraSeats::Plan) {
                throw new DomainException("Car {$seat->run->vehicle->registration_number} does not take people on a rider's plan.");
            }

            $room = $seat->run->seatsFree() + $seat->party;

            if ($party > $room) {
                throw new DomainException("There is room for {$room} on that van, not {$party}.");
            }
        }
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
