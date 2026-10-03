<?php

use Illuminate\Support\Carbon;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Models\Plan;

beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 09:00:00'); // a Monday

    $this->network = network();
    $this->run = run($this->network);
    $this->account = Account::create(['phone' => '+233241234567'])->assignRole('rider');
    $this->withToken($this->account->createToken('test')->plainTextToken);
});

function applyViaApi(array $overrides = [])
{
    return test()->postJson('/api/rider/v1/application', [
        'name' => 'Ama Owusu',
        'route_id' => test()->network['route']->id,
        'stop_id' => test()->network['stops'][1]->id,
        'morning_time' => '06:30',
        ...$overrides,
    ]);
}

it('gives a brand new rider an empty home', function () {
    $this->getJson('/api/rider/v1/home')
        ->assertOk()
        ->assertJsonPath('rider', null)
        ->assertJsonPath('application', null)
        ->assertJsonPath('seats', [])
        ->assertJsonPath('subscription', null)
        ->assertJsonPath('ride_dates', [])
        ->assertJsonPath('deletion_requested', false);
});

it('lists routes with their pickup stops and the times a van runs', function () {
    run($this->network, ['direction' => 'return', 'departs_at' => '17:30', 'seats_held' => 1]);
    $empty = network(); // a route with no run is not offered

    $routes = $this->getJson('/api/rider/v1/routes')->assertOk()->json('routes');

    expect($routes)->toHaveCount(1)
        ->and(array_column($routes[0]['stops'], 'name'))->toBe(['Barrier', 'Old Barrier', 'Mallam']) // not Circle, the destination
        ->and($routes[0]['morning_times'])->toBe([['time' => '06:30', 'seats_free' => true]])
        ->and($routes[0]['evening_times'])->toBe([['time' => '17:30', 'seats_free' => true]]);
});

it('takes an application, shows it on home, and lets her withdraw it', function () {
    applyViaApi()->assertCreated()->assertJsonPath('application.status', 'pending')->assertJsonPath('application.stop.name', 'Old Barrier');

    $this->getJson('/api/rider/v1/home')
        ->assertJsonPath('rider.name', 'Ama Owusu')
        ->assertJsonPath('application.status', 'pending')
        ->assertJsonPath('application.morning_time', '06:30');

    applyViaApi()->assertStatus(422)->assertJsonPath('message', 'You already have an application waiting for review.');

    $this->deleteJson('/api/rider/v1/application')->assertNoContent();
    $this->getJson('/api/rider/v1/home')->assertJsonPath('application.status', 'withdrawn');
});

it('validates an application', function () {
    applyViaApi(['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
    applyViaApi(['morning_time' => '6.30am'])->assertStatus(422)->assertJsonValidationErrors('morning_time');
    applyViaApi(['morning_time' => null])->assertStatus(422)->assertJsonPath('message', 'Choose a morning time, an evening time, or both.');
    applyViaApi(['stop_id' => $this->network['stops'][3]->id + 99])->assertStatus(422);
});

it('shows her seat once approved, and her subscription once active', function () {
    applyViaApi();
    $rider = Rider::first();
    $operator = staff(Staff::SUPER_ADMIN);

    app(ApproveApplicationWithSeats::class)->handle($rider->applications()->first(), [$this->run->id], $operator);

    $this->getJson('/api/rider/v1/home')
        ->assertJsonPath('application.status', 'approved')
        ->assertJsonPath('seats.0.status', 'reserved')
        ->assertJsonPath('seats.0.stop', 'Old Barrier')
        ->assertJsonPath('seats.0.time', '06:40')
        ->assertJsonPath('seats.0.driver_name', 'Kwame Mensah')
        ->assertJsonPath('seats.0.car_number', $this->network['vehicle']->registration_number)
        ->assertJsonPath('seats.0.car_colour', 'White')
        ->assertJsonPath('subscription', null);

    app(ActivateSubscription::class)->handle($rider, Plan::where('name', '1 month')->first(), $operator);

    $home = $this->getJson('/api/rider/v1/home')
        ->assertJsonPath('seats.0.status', 'active')
        ->assertJsonPath('subscription.plan', '1 month')
        ->assertJsonPath('subscription.price_label', 'GHS 60')
        ->assertJsonPath('subscription.ends_on', '2026-11-03')
        ->assertJsonPath('subscription.days_left', 30)
        ->json();

    expect($home['ride_dates'])->toHaveCount(14)->and($home['ride_dates'][0])->toBe('2026-10-05');
});

it('previews a notice, then records it and credits the day', function () {
    applyViaApi();
    $rider = Rider::first();
    $operator = staff(Staff::SUPER_ADMIN);
    app(ApproveApplicationWithSeats::class)->handle($rider->applications()->first(), [$this->run->id], $operator);
    app(ActivateSubscription::class)->handle($rider, Plan::where('name', '1 month')->first(), $operator);

    // Thursday, asked on Monday morning: in time. Today, asked at 09:00 after a 06:30 van: late.
    $this->getJson('/api/rider/v1/absences/preview?date=2026-10-08')
        ->assertOk()->assertJsonPath('in_time', true)->assertJsonPath('credited', true);

    $this->getJson('/api/rider/v1/absences/preview?date=2026-10-05')
        ->assertOk()->assertJsonPath('in_time', false)->assertJsonPath('credited', false);

    $this->getJson('/api/rider/v1/home')->assertJsonPath('subscription.ends_on', '2026-11-03'); // a preview saves nothing

    $this->postJson('/api/rider/v1/absences', ['date' => '2026-10-08'])
        ->assertCreated()->assertJsonPath('notice.credited', true);

    $home = $this->getJson('/api/rider/v1/home')
        ->assertJsonPath('subscription.ends_on', '2026-11-04')
        ->assertJsonPath('subscription.credited_days', 1)
        ->assertJsonPath('notices.0.date', '2026-10-08')
        ->json();

    expect($home['ride_dates'])->not->toContain('2026-10-08');

    $this->postJson('/api/rider/v1/absences', ['date' => '2026-10-08'])->assertStatus(422);
    $this->postJson('/api/rider/v1/absences', ['date' => 'next thursday'])->assertStatus(422)->assertJsonValidationErrors('date');
});

it('lists the plans on sale', function () {
    Plan::where('name', '2 weeks')->update(['is_active' => false]);

    $plans = $this->getJson('/api/rider/v1/plans')->assertOk()->json('plans');

    expect(array_column($plans, 'name'))->toBe(['1 month', '3 months'])
        ->and($plans[0]['price'])->toBe(6000)
        ->and($plans[0]['price_label'])->toBe('GHS 60');
});

it('never shows one rider another rider\'s data', function () {
    $other = application($this->network, 'Esi Boateng');
    app(ApproveApplicationWithSeats::class)->handle($other, [$this->run->id], staff(Staff::SUPER_ADMIN));

    $this->getJson('/api/rider/v1/home')->assertJsonPath('rider', null)->assertJsonPath('seats', []);
    $this->postJson('/api/rider/v1/absences', ['date' => '2026-10-08'])->assertStatus(422)->assertJsonPath('message', 'Apply for a seat first.');
});

it('keeps a driver out of the rider endpoints', function () {
    $driver = Account::create(['phone' => '+233209999999'])->assignRole('driver');
    $this->app['auth']->forgetGuards();

    $this->withToken($driver->createToken('test')->plainTextToken)->getJson('/api/rider/v1/home')->assertForbidden();
});
