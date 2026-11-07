<?php

namespace Modules\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Drivers\Enums\ExtraSeats;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\GuestSeats;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

/**
 * A rider holds seats on her van for a day, for people travelling with her. Each pays the usual fare to the mate.
 *
 * Only on vans set to "they pay the fare", only on a day her subscription covers and she rides, and only until
 * the van reaches her stop. Holding again for the same day changes the number. Zero lets the seats go.
 */
class HoldGuestSeats
{
    public function handle(Seat $seat, CarbonInterface $date, int $seats, ?CarbonInterface $now = null): ?GuestSeats
    {
        $now = CarbonImmutable::parse($now ?? now());
        $date = CarbonImmutable::parse($date)->startOfDay();
        $seat->loadMissing('run.vehicle', 'run.route.stops');
        $run = $seat->run;
        $max = (int) config('subscriptions.extra_seats_max');

        $this->ensureCanHold($seat, $run, $date, $now);

        if ($seats < 0 || $seats > $max) {
            throw new DomainException("You can hold up to {$max} seats for people with you.");
        }

        return DB::transaction(function () use ($seat, $run, $date, $seats) {
            // Lock the run, so two riders can't both take the last seat.
            Run::query()->lockForUpdate()->find($run->id);
            $held = GuestSeats::query()->where('rider_id', $seat->rider_id)->where('run_id', $run->id)->where('date', $date->toDateString())->first();

            if ($seats === 0) {
                $held?->delete();

                return null;
            }

            $free = $run->guestSeatsFree($date, exceptRiderId: $seat->rider_id);

            if ($seats > $free) {
                throw new DomainException($free === 0 ? 'The van is full that day.' : "Only {$free} more ".($free === 1 ? 'seat is' : 'seats are').' free that day.');
            }

            return GuestSeats::query()->updateOrCreate(
                ['rider_id' => $seat->rider_id, 'run_id' => $run->id, 'date' => $date->toDateString()],
                ['seats' => $seats],
            );
        });
    }

    private function ensureCanHold(Seat $seat, Run $run, CarbonImmutable $date, CarbonImmutable $now): void
    {
        if ($seat->status !== SeatStatus::Active) {
            throw new DomainException('Pay for your seat first.');
        }

        if ($run->vehicle->extra_seats !== ExtraSeats::Fare) {
            throw new DomainException('This van does not hold seats for people with you.');
        }

        if ($date->lt($now->startOfDay()) || $date->gt($now->startOfDay()->addDays((int) config('subscriptions.guest_days_ahead')))) {
            throw new DomainException('Choose a day in the next two weeks.');
        }

        if (! $run->is_active || ! $run->runsOn($date)) {
            throw new DomainException('Your van doesn\'t run that day.');
        }

        if (! Subscription::query()->where('rider_id', $seat->rider_id)->covering($date)->exists()) {
            throw new DomainException('You have no subscription on that day.');
        }

        if (AbsenceNotice::query()->where('rider_id', $seat->rider_id)->where('date', $date->toDateString())->where('source', 'rider')->exists()) {
            throw new DomainException('You said you won\'t ride that day.');
        }

        // Once the van has reached her stop it is too late to change who is coming.
        if ($date->isSameDay($now) && $now->gt($date->setTimeFromTimeString($run->timeAtStop($seat->stop_id) ?? $run->departureTime()))) {
            throw new DomainException('Your van has already passed your stop today.');
        }
    }
}
