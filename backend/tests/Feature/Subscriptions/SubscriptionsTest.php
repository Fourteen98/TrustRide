<?php

use Illuminate\Support\Carbon;
use Modules\Access\Models\Staff;
use Modules\Messaging\Models\EventLog;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Actions\RunDailyUpkeep;
use Modules\Subscriptions\Enums\NoShowPolicy;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Queries\TodaysRiders;

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00'); // a Monday

    $this->network = network();
    $this->run = run($this->network);
    $this->operator = staff(Staff::SUPER_ADMIN);
    $this->month = Plan::where('name', '1 month')->first();
});

/** An approved rider with a reserved seat on the test run. */
function seated(string $name = 'Ama Owusu', int $stop = 0): Rider
{
    $application = application(test()->network, $name, ['stop_id' => test()->network['stops'][$stop]->id]);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], test()->operator);

    return $application->rider;
}

it('ships the three agreed plans', function () {
    expect(Plan::orderBy('duration_days')->get()->map(fn ($plan) => [$plan->name, $plan->duration_days, $plan->price])->all())
        ->toBe([['2 weeks', 14, 3500], ['1 month', 30, 6000], ['3 months', 90, 15000]])
        ->and($this->month->no_show_policy)->toBe(NoShowPolicy::CreditWithNotice)
        ->and($this->month->trips_per_day)->toBe(1);
});

it('activates a subscription, makes the seat active and sends the receipt details', function () {
    $rider = seated();

    $subscription = app(ActivateSubscription::class)->handle($rider, $this->month, $this->operator);

    expect($subscription->starts_on->toDateString())->toBe('2026-10-05')
        ->and($subscription->ends_on->toDateString())->toBe('2026-11-03')
        ->and($subscription->price)->toBe(6000)
        ->and($subscription->activated_by)->toBe($this->operator->id)
        ->and(Seat::first()->status)->toBe(SeatStatus::Active);

    $receipt = EventLog::where('name', 'subscription.activated')->first()->payload;

    expect($receipt['car_number'])->toBe($this->network['vehicle']->registration_number)
        ->and($receipt['car_colour'])->toBe('White')
        ->and($receipt['driver_name'])->toBe('Kwame Mensah')
        ->and($receipt['stop'])->toBe('Barrier')
        ->and($receipt['time'])->toBe('06:30')
        ->and($receipt['price'])->toBe('GHS 60');
});

it('activates the same way with no operator, as a payment will', function () {
    $subscription = app(ActivateSubscription::class)->handle(seated(), $this->month);

    expect($subscription->activated_by)->toBeNull()
        ->and(EventLog::where('name', 'subscription.activated')->count())->toBe(1);
});

it('refuses to activate without a seat, or on a plan that covers fewer trips than she holds', function () {
    $noSeat = application($this->network, 'Esi')->rider;

    expect(fn () => app(ActivateSubscription::class)->handle($noSeat, $this->month))->toThrow(DomainException::class, 'no seat');

    $evening = run($this->network, ['direction' => 'return', 'departs_at' => '17:30']);
    $application = application($this->network, 'Abena');
    app(ApproveApplicationWithSeats::class)->handle($application, [$this->run->id, $evening->id], $this->operator);

    expect(fn () => app(ActivateSubscription::class)->handle($application->rider, $this->month))->toThrow(DomainException::class, '2 seats');

    $bothWays = Plan::create(['name' => '1 month both ways', 'duration_days' => 30, 'price' => 11000, 'trips_per_day' => 2]);

    expect(app(ActivateSubscription::class)->handle($application->rider, $bothWays)->trips_per_day)->toBe(2);
});

it('keeps what she bought when the plan changes later', function () {
    $subscription = app(ActivateSubscription::class)->handle(seated(), $this->month);

    $this->month->update(['price' => 8000, 'no_show_policy' => NoShowPolicy::LoseDay]);

    expect($subscription->fresh()->price)->toBe(6000)
        ->and($subscription->fresh()->no_show_policy)->toBe(NoShowPolicy::CreditWithNotice);
});

it('starts a renewal the day after the current one ends', function () {
    $rider = seated();
    $first = app(ActivateSubscription::class)->handle($rider, $this->month);

    Carbon::setTestNow('2026-10-30 09:00:00');
    $renewal = app(ActivateSubscription::class)->handle($rider, $this->month);

    expect($renewal->starts_on->toDateString())->toBe('2026-11-04')
        ->and($renewal->ends_on->toDateString())->toBe('2026-12-03')
        ->and($first->fresh()->ends_on->toDateString())->toBe('2026-11-03');
});

it('credits a day when she tells us in time, and not when she is late', function () {
    $rider = seated();
    $subscription = app(ActivateSubscription::class)->handle($rider, $this->month);

    // The van leaves at 06:30 and the cut-off is 12 hours, so the deadline for Wednesday is Tuesday 18:30.
    $inTime = app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-07'), Carbon::parse('2026-10-06 18:00'));

    expect($inTime->in_time)->toBeTrue()->and($inTime->credited)->toBeTrue()
        ->and($subscription->fresh()->ends_on->toDateString())->toBe('2026-11-04')
        ->and($subscription->fresh()->credited_days)->toBe(1);

    $late = app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-08'), Carbon::parse('2026-10-07 19:00'));

    expect($late->in_time)->toBeFalse()->and($late->credited)->toBeFalse()
        ->and($subscription->fresh()->ends_on->toDateString())->toBe('2026-11-04');
});

it('follows the rule the subscription was bought with', function (NoShowPolicy $policy, bool $inTimeCredited, bool $lateCredited) {
    $this->month->update(['no_show_policy' => $policy]);
    $rider = seated();
    app(ActivateSubscription::class)->handle($rider, $this->month);

    $early = app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-07'), Carbon::parse('2026-10-06 12:00'));
    $late = app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-08'), Carbon::parse('2026-10-08 06:00'));

    expect($early->credited)->toBe($inTimeCredited)->and($late->credited)->toBe($lateCredited);
})->with([
    'she loses the day' => [NoShowPolicy::LoseDay, false, false],
    'credit with notice' => [NoShowPolicy::CreditWithNotice, true, false],
    'always credit' => [NoShowPolicy::CreditAlways, true, true],
]);

it('refuses a notice for a past day, a day twice, a day with no subscription, and a day the van does not run', function () {
    $rider = seated();
    $this->run->update(['days_of_week' => [1, 2, 3, 4, 5]]);
    app(ActivateSubscription::class)->handle($rider, $this->month);

    app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-07'));

    expect(fn () => app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-07')))->toThrow(DomainException::class, 'already told us')
        ->and(fn () => app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-04')))->toThrow(DomainException::class, 'passed')
        ->and(fn () => app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-12-25')))->toThrow(DomainException::class, 'no subscription')
        ->and(fn () => app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-10')))->toThrow(DomainException::class, 'doesn\'t run'); // a Saturday
});

it('moves a renewal along when a day is credited on the current subscription', function () {
    $rider = seated();
    app(ActivateSubscription::class)->handle($rider, $this->month);
    $renewal = app(ActivateSubscription::class)->handle($rider, $this->month);

    app(GiveAbsenceNotice::class)->handle($rider, Carbon::parse('2026-10-09'), Carbon::parse('2026-10-06 12:00'));

    expect($renewal->fresh()->starts_on->toDateString())->toBe('2026-11-05')
        ->and($renewal->fresh()->ends_on->toDateString())->toBe('2026-12-04');
});

it('lists today\'s riders in stop order and flags who is away', function () {
    $esi = seated('Esi', stop: 2);
    $ama = seated('Ama', stop: 0);
    $yaa = seated('Yaa', stop: 1);
    $unpaid = seated('Unpaid', stop: 1);

    foreach ([$esi, $ama, $yaa] as $rider) {
        app(ActivateSubscription::class)->handle($rider, $this->month);
    }

    app(GiveAbsenceNotice::class)->handle($yaa, Carbon::parse('2026-10-05'));

    $list = app(TodaysRiders::class)->handle($this->run);

    expect($list->pluck('name')->all())->toBe(['Ama', 'Yaa', 'Esi'])
        ->and($list->pluck('time')->all())->toBe(['06:30', '06:40', '06:50'])
        ->and($list->pluck('absent')->all())->toBe([false, true, false])
        ->and(app(TodaysRiders::class)->handle($this->run, Carbon::parse('2027-01-01')))->toBeEmpty();

    $this->run->update(['days_of_week' => [2, 3]]);
    expect(app(TodaysRiders::class)->handle($this->run->fresh()))->toBeEmpty(); // today is a Monday
});

it('warns once before the end, expires after it, and frees the seat after the grace period', function () {
    $rider = seated();
    $subscription = app(ActivateSubscription::class)->handle($rider, $this->month); // ends 3 November

    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-10-30')))->toBe(['expiring' => 0, 'expired' => 0, 'released' => 0]);

    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-10-31'))['expiring'])->toBe(1)
        ->and(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-01'))['expiring'])->toBe(0)
        ->and(EventLog::where('name', 'subscription.expiring')->count())->toBe(1);

    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-04')))->toBe(['expiring' => 0, 'expired' => 1, 'released' => 0])
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(Seat::first()->status)->toBe(SeatStatus::Active);

    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-05'))['released'])->toBe(0)
        ->and(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-06'))['released'])->toBe(1)
        ->and(Seat::first()->status)->toBe(SeatStatus::Released)
        ->and($this->run->seatsFree())->toBe(12);
});

it('does not warn or free the seat of a rider who has renewed', function () {
    $rider = seated();
    app(ActivateSubscription::class)->handle($rider, $this->month);
    app(ActivateSubscription::class)->handle($rider, $this->month);

    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-01'))['expiring'])->toBe(0)
        ->and(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-11-10')))->toBe(['expiring' => 0, 'expired' => 1, 'released' => 0]);
});

it('gives up a reserved seat that was never paid for after a week', function () {
    seated();

    // Reserved on the 5th. Seven full days have passed by the 13th.
    expect(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-10-12'))['released'])->toBe(0)
        ->and(app(RunDailyUpkeep::class)->handle(Carbon::parse('2026-10-13'))['released'])->toBe(1);
});
