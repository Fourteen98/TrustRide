<?php

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Messaging\Models\Message;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Filament\Resources\TripResource\Pages\ManageTrips;
use Modules\Trips\Models\LocationPing;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/*
 * A morning on the network() fixture: Barrier, Old Barrier, Mallam, Circle. Ama gets on at Barrier,
 * Esi and Abena at Mallam. The driver and the mate each have a phone.
 */
beforeEach(function () {
    syncAccess();
    Carbon::setTestNow('2026-10-05 06:25:00'); // a Monday, five minutes before the run

    $this->network = network();
    // Pins roughly a kilometre apart, heading north-east.
    $this->network['stops']->each(fn ($stop, $i) => $stop->update(['latitude' => 5.55 + $i * 0.01, 'longitude' => -0.30 + $i * 0.01]));
    $this->run = run($this->network);
    $this->operator = staff('operator');

    $this->driver = $this->network['driver']->account;
    $this->mate = app(RegisterMate::class)->handle($this->network['driver'], 'Kofi Asante', '0240000002')->account;

    $this->riders = collect([['Ama Owusu', 0], ['Esi Boateng', 2], ['Abena Darko', 2]])->mapWithKeys(function (array $row) {
        $application = application($this->network, $row[0], ['stop_id' => $this->network['stops'][$row[1]]->id]);
        app(ApproveApplicationWithSeats::class)->handle($application, [$this->run->id], $this->operator);
        app(ActivateSubscription::class)->handle($application->rider, Plan::query()->where('duration_days', 30)->first(), $this->operator);

        return [$row[0] => $application->rider];
    });
});

/** The next request comes from this account's phone. */
function phone(Account $account)
{
    app('auth')->forgetGuards(); // or the guard keeps answering as whoever asked last

    return test()->withToken($account->createToken('t')->plainTextToken);
}

function ping(int $stopIndex, ?string $at = null): array
{
    $stop = test()->network['stops'][$stopIndex];

    return ['latitude' => $stop->latitude + 0.0002, 'longitude' => $stop->longitude, 'accuracy' => 12, 'recorded_at' => $at ?? now()->toIso8601String()];
}

it('shows the crew today\'s riders by stop, in the order the van reaches them', function () {
    app(GiveAbsenceNotice::class)->handle($this->riders['Abena Darko'], Carbon::parse('2026-10-05'));

    $run = phone($this->mate)->getJson('/api/driver/v1/today')->assertOk()
        ->assertJsonPath('ping_interval_seconds', 15)
        ->assertJsonCount(1, 'runs')
        ->json('runs.0');

    expect($run['status'])->toBe('not_started')
        ->and(array_column($run['stops'], 'name'))->toBe(['Barrier', 'Old Barrier', 'Mallam']) // not Circle, where everyone gets off
        ->and($run['stops'][0]['riders'])->toBe([['rider_id' => $this->riders['Ama Owusu']->id, 'name' => 'Ama Owusu', 'absent' => false, 'pickup' => null]])
        ->and(array_column($run['stops'][2]['riders'], 'name'))->toBe(['Abena Darko', 'Esi Boateng'])
        ->and($run['stops'][2]['riders'][0]['absent'])->toBeTrue();

    // The driver sees the same run.
    phone($this->driver)->getJson('/api/driver/v1/today')->assertJsonPath('runs.0.run_id', $this->run->id);
});

it('starts the trip once, tells the riders who are riding, and ends it', function () {
    app(GiveAbsenceNotice::class)->handle($this->riders['Abena Darko'], Carbon::parse('2026-10-05'));

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id])->assertCreated()->assertJsonPath('run.status', 'running');
    phone($this->mate)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id])->assertCreated();

    expect(Trip::count())->toBe(1)
        ->and(Trip::first()->started_by_account)->toBe($this->driver->id)
        ->and(Message::query()->where('body', 'like', '%has set off%')->pluck('recipient_name')->sort()->values()->all())->toBe(['Ama Owusu', 'Esi Boateng']);

    phone($this->driver)->postJson('/api/driver/v1/trip/end', ['run_id' => $this->run->id])->assertOk()->assertJsonPath('run.status', 'ended');
    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id])->assertStatus(422)->assertJsonPath('message', 'Today\'s trip has already ended.');
});

it('keeps a phone to its own van\'s runs', function () {
    $other = network();
    $otherRun = run($other);

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $otherRun->id])->assertStatus(422)->assertJsonPath('message', 'That run is not yours.');

    $rider = Account::create(['phone' => '+233209990000'])->assignRole('rider');
    phone($rider)->getJson('/api/driver/v1/today')->assertForbidden();
    phone($rider)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(0)]])->assertForbidden();
});

it('lets the mate mark pickups, change his mind, and not the driver', function () {
    $ama = $this->riders['Ama Owusu']->id;
    $mark = fn (Account $who, int $riderId, string $status) => phone($who)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $riderId, 'status' => $status]);

    $mark($this->mate, $ama, 'picked_up')->assertStatus(422)->assertJsonPath('message', 'Start the trip first.');
    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);

    $mark($this->driver, $ama, 'picked_up')->assertForbidden();
    $mark($this->mate, $ama, 'missed')->assertOk()->assertJsonPath('run.stops.0.riders.0.pickup', 'missed')->assertJsonPath('run.stops.0.reached', true);
    $mark($this->mate, $ama, 'missed')->assertOk();
    $mark($this->mate, $ama, 'picked_up')->assertOk()->assertJsonPath('run.stops.0.riders.0.pickup', 'picked_up');

    expect(TripPickup::count())->toBe(1)
        ->and(Message::query()->where('body', 'like', '%did not find you%')->count())->toBe(1); // told once, not on the repeat

    $stranger = Rider::query()->create(['account_id' => Account::create(['phone' => '+233209990001'])->id, 'name' => 'Not On This Run']);
    $mark($this->mate, $stranger->id, 'picked_up')->assertStatus(422);
});

it('takes positions from both phones, keeps the newest, and moves the van along the stops', function () {
    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    $send = fn (Account $who, array $pings) => phone($who)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => $pings]);

    $this->travel(2)->minutes();
    $send($this->driver, [ping(0)])->assertOk()->assertJson(['kept' => 1, 'running' => true]);
    expect(Trip::first()->reached_stop_id)->toBe($this->network['stops'][0]->id);

    // The mate's phone was offline. It sends an old batch late: history is kept, the position is not moved back.
    $this->travel(5)->minutes();
    $send($this->driver, [ping(2)]);
    $send($this->mate, [ping(1, now()->subMinutes(3)->toIso8601String()), ping(0, now()->subMinutes(4)->toIso8601String())])->assertJson(['kept' => 2]);

    $trip = Trip::first();
    expect($trip->reached_stop_id)->toBe($this->network['stops'][2]->id)
        ->and(round($trip->latitude, 4))->toBe(round($this->network['stops'][2]->latitude + 0.0002, 4))
        ->and(LocationPing::count())->toBe(4)
        ->and(LocationPing::query()->distinct()->count('account_id'))->toBe(2);

    // A wrong clock: a reading from before the trip and one from tomorrow are dropped.
    $send($this->mate, [ping(3, now()->subDay()->toIso8601String()), ping(3, now()->addDay()->toIso8601String())])->assertJson(['kept' => 0]);

    phone($this->driver)->postJson('/api/driver/v1/trip/end', ['run_id' => $this->run->id]);
    $send($this->driver, [ping(3)])->assertOk()->assertJson(['kept' => 0, 'running' => false]);
    $send($this->driver, [['latitude' => 200, 'longitude' => 0, 'recorded_at' => now()->toIso8601String()]])->assertStatus(422);
});

it('tells a rider how many stops away her van is', function () {
    $esi = $this->riders['Esi Boateng']->account;

    phone($esi)->getJson('/api/rider/v1/trips')->assertOk()
        ->assertJsonPath('trips.0.status', 'not_started')
        ->assertJsonPath('trips.0.stops_away', null)
        ->assertJsonPath('trips.0.my_stop', 'Mallam')
        ->assertJsonPath('trips.0.map_url', null);

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.status', 'running')->assertJsonPath('trips.0.stops_away', 3)->assertJsonPath('trips.0.position', null);

    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(0)]]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.stops_away', 2)->assertJsonPath('trips.0.position.live', true)->assertJsonPath('trips.0.stops.0.reached', true);

    phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Esi Boateng']->id, 'status' => 'picked_up']);
    $trip = phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.stops_away', 0)->assertJsonPath('trips.0.pickup', 'picked_up')->json('trips.0');
    expect($trip['map_url'])->toContain('/t/'.Trip::first()->public_id);

    $this->travel(5)->minutes();
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.position.live', false);

    phone($this->driver)->postJson('/api/driver/v1/trip/end', ['run_id' => $this->run->id]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.status', 'ended')->assertJsonPath('trips.0.position', null)->assertJsonPath('trips.0.stops_away', null);

    // Someone with no seat has nothing to follow.
    phone(Account::create(['phone' => '+233209990002'])->assignRole('rider'))->getJson('/api/rider/v1/trips')->assertOk()->assertJsonPath('trips', []);
});

it('travels a return run backwards along the route', function () {
    $back = run($this->network, ['direction' => 'return', 'departs_at' => '17:30']);
    app(ApproveApplicationWithSeats::class)->handle(application($this->network, 'Yaa Mensah', ['stop_id' => $this->network['stops'][1]->id, 'morning_time' => null, 'evening_time' => '17:30']), [$back->id], $this->operator);

    $run = collect(phone($this->driver)->getJson('/api/driver/v1/today')->json('runs'))->firstWhere('run_id', $back->id);

    expect(array_column($run['stops'], 'name'))->toBe(['Circle', 'Mallam', 'Old Barrier']);
});

it('shows the live map page to anyone with the link, with no rider named, and hides the van once the trip ends', function () {
    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(1)]]);
    $trip = Trip::first();
    $this->flushHeaders();

    $this->get("/t/{$trip->public_id}")->assertOk()->assertSee('Bortianor to Circle')->assertSee($this->network['vehicle']->registration_number)->assertDontSee('Ama Owusu');

    $data = $this->getJson("/t/{$trip->public_id}/data?stop={$this->network['stops'][2]->id}")->assertOk()
        ->assertJsonPath('status', 'running')->assertJsonPath('stops_away', 1)->assertJsonPath('position.live', true)->json();
    expect(json_encode($data))->not->toContain('Ama')->not->toContain('+233');

    $this->get('/t/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    $this->get("/t/{$trip->id}")->assertNotFound(); // the plain id is not a link

    app(EndTrip::class)->handle($trip);
    $this->getJson("/t/{$trip->public_id}/data")->assertJsonPath('status', 'ended')->assertJsonPath('position', null);
});

it('lets an operator start and end a trip by hand, and cleans up overnight', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->operator, 'platform');

    Livewire::test(ManageTrips::class)->callAction('start', ['run_id' => $this->run->id])->assertHasNoActionErrors();
    $trip = Trip::first();
    expect($trip)->started_by_staff->toBe($this->operator->id)->status->toBe(TripStatus::Running);

    $this->get('/admin/trips')->assertOk()->assertSee('Bortianor to Circle');
    Livewire::test(ManageTrips::class)->callTableAction('end', $trip);
    expect($trip->fresh())->status->toBe(TripStatus::Ended)->ended_by_staff->toBe($this->operator->id);

    // A trip nobody ended is closed after midnight, and old positions are deleted.
    $trip->update(['status' => TripStatus::Running, 'ended_at' => null]);
    LocationPing::query()->insert(['trip_id' => $trip->id, 'latitude' => 5.5, 'longitude' => -0.2, 'recorded_at' => now(), 'created_at' => now()]);
    $this->travel(1)->days();
    $this->artisan('trips:end-stale')->assertSuccessful();
    expect($trip->fresh()->status)->toBe(TripStatus::Ended);

    $this->artisan('trips:prune')->assertSuccessful();
    expect(LocationPing::count())->toBe(1);
    $this->travel(31)->days();
    $this->artisan('trips:prune')->assertSuccessful();
    expect(LocationPing::count())->toBe(0);
});

it('gives the day back for a missed pickup only when her plan says so, and takes it back if the mate corrects it', function () {
    $ama = $this->riders['Ama Owusu'];
    $esi = $this->riders['Esi Boateng'];
    // Ama's subscription has the rule switched on. Esi's has the default: a message and nothing more.
    $amaSub = Subscription::query()->where('rider_id', $ama->id)->sole();
    $amaSub->update(['credit_missed_pickup' => true]);
    $esiSub = Subscription::query()->where('rider_id', $esi->id)->sole();
    [$amaEnds, $esiEnds] = [$amaSub->ends_on, $esiSub->ends_on];

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    $mark = fn (int $riderId, string $status) => phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $riderId, 'status' => $status])->assertOk();

    $mark($ama->id, 'missed');
    $mark($ama->id, 'missed');
    $mark($esi->id, 'missed');

    expect($amaSub->fresh())->ends_on->toEqual($amaEnds->addDay())->credited_days->toBe(1)
        ->and($esiSub->fresh())->ends_on->toEqual($esiEnds)->credited_days->toBe(0)
        ->and(AbsenceNotice::query()->where('source', 'missed_pickup')->count())->toBe(1);

    $mark($ama->id, 'picked_up'); // the mate found her after all

    expect($amaSub->fresh())->ends_on->toEqual($amaEnds)->credited_days->toBe(0)
        ->and(AbsenceNotice::count())->toBe(0);
});
