<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Access\Models\Account;
use Modules\Drivers\Enums\ExtraSeats;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Jobs\SettleFakePayment;
use Modules\Payments\Models\Payment;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Models\GuestSeats;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Queries\TodaysRiders;

/*
 * People travelling with a rider. Each van says whether that is allowed and how they pay:
 * the fare to the mate, held day by day, or on her plan, paid with it.
 */
beforeEach(function () {
    syncAccess();
    Queue::fake([SettleFakePayment::class]); // the fake gateway's own settling; each test settles by hand
    Carbon::setTestNow('2026-10-05 05:00:00'); // a Monday, before the 06:30 run
    config(['payments.driver' => 'fake']);

    $this->network = network();
    $this->run = run($this->network);
    $this->vehicle = $this->network['vehicle'];
    $this->plan = Plan::query()->where('duration_days', 30)->firstOrFail();
});

/** A rider with an approved seat at Mallam, logged in on the next request. Paid for a month unless told not to. */
function companion(string $phone, bool $paid = true): Account
{
    $account = Account::create(['phone' => $phone])->assignRole('rider');
    $application = app(SubmitApplication::class)->handle($account, [
        'name' => 'Rider '.substr($phone, -3), 'route_id' => test()->network['route']->id,
        'stop_id' => test()->network['stops'][2]->id, 'morning_time' => '06:30',
    ]);
    app(ApproveApplicationWithSeats::class)->handle($application, [test()->run->id], staff('operator'));

    if ($paid) {
        test()->actingAs($account, 'app')->postJson('/api/rider/v1/purchase', ['plan_id' => test()->plan->id, 'network' => 'mtn'])->assertCreated();
        payNow();
    }

    test()->actingAs($account, 'app');

    return $account;
}

function payNow(): void
{
    app(ApplyOutcome::class)->handle(Payment::query()->latest('id')->firstOrFail(), true, 'FAKE-'.Payment::query()->max('id'), 'fake');
}

function seatOf(Account $account): Seat
{
    return Seat::query()->whereHas('rider', fn ($query) => $query->where('account_id', $account->id))->firstOrFail();
}

it('holds seats for a day on vans where people pay the fare, and the mate sees them', function () {
    $esi = companion('+233241110001');
    $hold = fn (int $seats, string $date = '2026-10-06') => $this->actingAs($esi, 'app')->putJson('/api/rider/v1/guests', ['seat_id' => seatOf($esi)->id, 'date' => $date, 'seats' => $seats]);

    $hold(2)->assertStatus(422)->assertJsonPath('message', 'This van does not hold seats for people with you.');

    $this->vehicle->update(['extra_seats' => ExtraSeats::Fare]);
    $hold(2)->assertOk()->assertJsonPath('seats', 2);
    $hold(3)->assertOk()->assertJsonPath('seats', 3); // changes the number, not a second booking
    expect(GuestSeats::query()->count())->toBe(1);

    $this->actingAs($esi, 'app')->getJson('/api/rider/v1/home')
        ->assertJsonPath('seats.0.extra_seats', 'fare')
        ->assertJsonPath('guests.0', ['run_id' => $this->run->id, 'date' => '2026-10-06', 'seats' => 3]);

    $tomorrow = app(TodaysRiders::class)->handle($this->run->fresh(), Carbon::parse('2026-10-06'))->firstWhere('name', 'Rider 001');
    expect($tomorrow['paying_fare'])->toBe(3)->and($tomorrow['on_plan'])->toBe(0);

    // The van has 15 seats: one is Esi's, three are her people's. A small van fills up.
    $this->vehicle->update(['seats_total' => 5]);
    $abena = companion('+233241110002');
    $this->actingAs($abena, 'app')->putJson('/api/rider/v1/guests', ['seat_id' => seatOf($abena)->id, 'date' => '2026-10-06', 'seats' => 2])
        ->assertStatus(422)->assertJsonPath('message', 'The van is full that day.');

    // Letting them go frees the seats.
    $hold(0)->assertOk()->assertJsonPath('seats', 0);
    expect(GuestSeats::query()->count())->toBe(0);

    // Not past the most allowed, not on a day she said she won't ride, not after the van passed her stop.
    $hold(4)->assertStatus(422);
    app(GiveAbsenceNotice::class)->handle(seatOf($esi)->rider, Carbon::parse('2026-10-07'));
    $hold(1, '2026-10-07')->assertStatus(422)->assertJsonPath('message', 'You said you won\'t ride that day.');
    $this->travelTo(Carbon::parse('2026-10-05 06:51'));
    $hold(1, '2026-10-05')->assertStatus(422)->assertJsonPath('message', 'Your van has already passed your stop today.');
});

it('adds people to her plan on vans that allow it, at the plan price for each', function () {
    $ama = companion('+233241110003', paid: false);
    $buy = fn (array $body = []) => $this->actingAs($ama, 'app')->postJson('/api/rider/v1/purchase', ['plan_id' => $this->plan->id, 'network' => 'mtn', ...$body]);

    $buy(['seats' => 3])->assertStatus(422)->assertJsonPath('message', "Car {$this->vehicle->registration_number} does not take people on a rider's plan.");

    $this->vehicle->update(['extra_seats' => ExtraSeats::Plan]);
    $buy(['seats' => 5])->assertStatus(422)->assertJsonPath('message', 'A plan can be for 1 to 4 people.');
    $buy(['seats' => 3])->assertCreated()->assertJsonPath('purchase.amount', 3 * $this->plan->price);
    payNow();

    $subscription = Subscription::query()->sole();
    expect($subscription->seats)->toBe(3)
        ->and($subscription->price)->toBe(3 * $this->plan->price)
        ->and(seatOf($ama)->party)->toBe(3)
        ->and($this->run->fresh()->seatsFree())->toBe(12 - 3);

    $mallam = app(TodaysRiders::class)->handle($this->run->fresh())->sole();
    expect($mallam['on_plan'])->toBe(2);

    // Renewing without saying keeps the three, at the same price.
    $buy()->assertCreated()->assertJsonPath('purchase.amount', 3 * $this->plan->price);
});

it('charges automatic renewals for everyone on her plan', function () {
    $this->vehicle->update(['extra_seats' => ExtraSeats::Plan]);
    $ama = companion('+233241110006', paid: false);

    $this->actingAs($ama, 'app')->postJson('/api/rider/v1/auto-renewal', ['plan_id' => $this->plan->id, 'network' => 'mtn', 'seats' => 2])
        ->assertCreated()
        ->assertJsonPath('auto_renewal.amount_label', 'GHS '.(2 * $this->plan->price / 100));
});

it('counts people on plans against the seats held on the run', function () {
    $this->vehicle->update(['extra_seats' => ExtraSeats::Plan]);
    $this->run->update(['seats_held' => 3]);
    $ama = companion('+233241110004', paid: false);
    companion('+233241110005', paid: false);

    // Two seats are taken, Ama's and the other rider's. Room for Ama and one more.
    $this->actingAs($ama, 'app')->postJson('/api/rider/v1/purchase', ['plan_id' => $this->plan->id, 'network' => 'mtn', 'seats' => 3])
        ->assertStatus(422)->assertJsonPath('message', 'There is room for 2 on that van, not 3.');
    $this->actingAs($ama, 'app')->postJson('/api/rider/v1/purchase', ['plan_id' => $this->plan->id, 'network' => 'mtn', 'seats' => 2])->assertCreated();
});
