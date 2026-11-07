<?php

use App\Support\Maps\GoogleRoutes;
use App\Support\Maps\Polyline;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\MarkStatementPaid;
use Modules\Drivers\Actions\RecordAssessment;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Drivers\Enums\AssessmentOutcome;
use Modules\Messaging\Models\Message;
use Modules\Network\Filament\Resources\RouteResource\Pages\ManageRoutes;
use Modules\Network\Models\Route;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Models\AbsenceNotice;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Filament\Pages\TripSimulator;
use Modules\Trips\Filament\Resources\TripResource\Pages\ManageTrips;
use Modules\Trips\Models\LocationPing;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;
use Modules\Trips\Queries\VansOnTheRoad;

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
        ->and(array_column($run['stops'], 'name'))->toBe(['Barrier', 'Old Barrier', 'Mallam', 'Circle'])
        ->and($run['stops'][0]['riders'])->toBe([['rider_id' => $this->riders['Ama Owusu']->id, 'name' => 'Ama Owusu', 'absent' => false, 'on_plan' => 0, 'paying_fare' => 0, 'gets_off' => 'Circle', 'pickup' => null]])
        // Everyone riding gets off at Circle, the end of the route. Abena isn't coming, so she gets off nowhere.
        ->and($run['stops'][3]['riders'])->toBe([])
        ->and(array_column($run['stops'][3]['getting_off'], 'name'))->toBe(['Ama Owusu', 'Esi Boateng'])
        ->and($run['stops'][3]['getting_off'][0]['on_board'])->toBeFalse()
        ->and($run['on_board'])->toBe([])
        ->and(array_column($run['stops'][2]['riders'], 'name'))->toBe(['Abena Darko', 'Esi Boateng'])
        ->and($run['stops'][2]['riders'][0]['absent'])->toBeTrue();

    // The driver sees the same run.
    phone($this->driver)->getJson('/api/driver/v1/today')->assertJsonPath('runs.0.run_id', $this->run->id)
        // The crew's map: every stop pinned, Circle included, and no van before the trip starts.
        ->assertJsonCount(4, 'runs.0.map.stops')
        ->assertJsonPath('runs.0.map.stops.3.name', 'Circle')
        ->assertJsonPath('runs.0.map.stops.0.latitude', 5.55)
        ->assertJsonPath('runs.0.map.position', null)
        ->assertJsonPath('runs.0.map.trail', [])
        ->assertJsonPath('runs.0.map.others', []);
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
        ->and(LocationPing::query()->distinct()->count('account_id'))->toBe(2)
        // Heading north-east along the pins, so the van icon turns that way.
        ->and($trip->heading())->toBeGreaterThan(35.0)->toBeLessThan(55.0);

    // On another van's crew map it shows, unnamed. On its own crew's map it is not an "other".
    expect(app(VansOnTheRoad::class)->handle()->sole())->toHaveKeys(['key', 'latitude', 'longitude', 'heading', 'live'])->not->toHaveKey('car_number')
        ->and(app(VansOnTheRoad::class)->handle($this->run->id))->toBeEmpty();

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
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.stops_away', 2)->assertJsonPath('trips.0.position.live', true)->assertJsonPath('trips.0.stops.0.reached', true)
        ->assertJsonPath('trips.0.position.heading', null); // one position: no way to tell which way it faces yet

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

it('shows a rider her route on a map even when no van is out', function () {
    phone($this->riders['Esi Boateng']->account)->getJson('/api/rider/v1/route-map')->assertOk()
        ->assertJsonPath('routes.0.my_stop', 'Mallam')
        ->assertJsonPath('routes.0.my_stop_id', $this->network['stops'][2]->id)
        ->assertJsonPath('routes.0.stops.0.latitude', 5.55)
        ->assertJsonPath('routes.0.stops.3.reached', false)
        ->assertJsonPath('routes.0.path', null);

    // No seat yet: nothing to draw but her own position.
    phone(Account::create(['phone' => '+233209990003'])->assignRole('rider'))->getJson('/api/rider/v1/route-map')->assertOk()->assertJsonPath('routes', []);
});

it('lists a rider\'s past trips with what happened at her stop', function () {
    // Abena says on Sunday that she won't ride on Tuesday.
    app(GiveAbsenceNotice::class)->handle($this->riders['Abena Darko'], Carbon::parse('2026-10-06'));

    foreach (['2026-10-05 06:25:00', '2026-10-06 06:25:00'] as $morning) {
        Carbon::setTestNow($morning);
        phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id])->assertSuccessful();
        phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Esi Boateng']->id, 'status' => 'picked_up']);
        phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Ama Owusu']->id, 'status' => 'missed']);
        phone($this->driver)->postJson('/api/driver/v1/trip/end', ['run_id' => $this->run->id]);
    }

    phone($this->riders['Esi Boateng']->account)->getJson('/api/rider/v1/trips/history')->assertOk()
        ->assertJsonCount(2, 'trips')
        ->assertJsonPath('trips.0.date', '2026-10-06')
        ->assertJsonPath('trips.0.outcome', 'picked_up')
        ->assertJsonPath('trips.0.status', 'ended')
        ->assertJsonPath('trips.0.my_stop', 'Mallam')
        ->assertJsonPath('has_more', false);

    phone($this->riders['Ama Owusu']->account)->getJson('/api/rider/v1/trips/history')->assertJsonPath('trips.0.outcome', 'missed')->assertJsonPath('trips.0.picked_up_at', null);
    phone($this->riders['Abena Darko']->account)->getJson('/api/rider/v1/trips/history')
        ->assertJsonPath('trips.0.outcome', 'not_riding')
        ->assertJsonPath('trips.1.outcome', 'not_marked');

    // Someone who never had a seat has no history.
    phone(Account::create(['phone' => '+233209990004'])->assignRole('rider'))->getJson('/api/rider/v1/trips/history')->assertOk()->assertJsonPath('trips', []);
});

it('finds the vans that go where she wants, getting on at the stop nearest her', function () {
    $evening = run($this->network, ['direction' => 'return', 'departs_at' => '17:30']);
    $rider = phone(Account::create(['phone' => '+233209990005'])->assignRole('rider'));
    $nearMallam = ['latitude' => 5.5703, 'longitude' => -0.2797];

    // To Circle in the morning: she gets on at Mallam, a few hundred metres away, and gets down at Circle.
    $runs = $rider->getJson('/api/rider/v1/search?'.http_build_query(['to' => 'circle', ...$nearMallam]))->assertOk()->json('runs');
    expect($runs)->toHaveCount(1)
        ->and($runs[0]['run_id'])->toBe($this->run->id)
        ->and($runs[0]['board']['name'])->toBe('Mallam')
        ->and($runs[0]['board']['metres_away'])->toBeLessThan(500)
        ->and($runs[0]['board']['time'])->toBe('06:50')
        ->and($runs[0]['alight']['name'])->toBe('Circle')
        ->and($runs[0]['apply_stop_id'])->toBe($this->network['stops'][2]->id)
        ->and($runs[0]['seats_free'])->toBe(9);

    // Home to Barrier in the evening: the return run, getting on at Mallam. Her application stop is where she gets down.
    $runs = $rider->getJson('/api/rider/v1/search?'.http_build_query(['to' => 'Old Barrier', ...$nearMallam]))->json('runs');
    expect(collect($runs)->firstWhere('run_id', $evening->id))
        ->board->name->toBe('Mallam')
        ->alight->name->toBe('Old Barrier')
        ->apply_stop_id->toBe($this->network['stops'][1]->id);

    // Without her position she gets on where the van starts.
    $rider->getJson('/api/rider/v1/search?to=Mallam')->assertJsonPath('runs.0.board.name', 'Barrier')->assertJsonPath('runs.0.board.metres_away', null);

    $rider->getJson('/api/rider/v1/search?to=Kumasi')->assertOk()->assertJsonPath('runs', []);
    $rider->getJson('/api/rider/v1/search?to=C')->assertStatus(422);
});

it('lets a rider say she got down, at the stop the van last reached', function () {
    // phone() logs that account in for the next request, so each call asks for it again.
    $esi = fn () => phone($this->riders['Esi Boateng']->account);
    $ama = fn () => phone($this->riders['Ama Owusu']->account);
    $alight = fn ($who) => $who()->postJson('/api/rider/v1/trips/alight', ['run_id' => $this->run->id]);

    $alight($esi)->assertStatus(422)->assertJsonPath('message', 'Your van has not set off today.');

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Ama Owusu']->id, 'status' => 'missed']);
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(3)]]);

    // The mate never marked Esi. Getting down at Circle says she rode.
    $this->travel(1)->minutes();
    $alight($esi)->assertOk()->assertJsonPath('stop', 'Circle');
    $first = TripPickup::query()->where('rider_id', $this->riders['Esi Boateng']->id)->sole();
    expect($first->status->value)->toBe('picked_up')->and($first->alighted_at)->not->toBeNull();

    // Twice is once.
    $this->travel(1)->minutes();
    $alight($esi)->assertOk();
    expect(TripPickup::query()->where('rider_id', $this->riders['Esi Boateng']->id)->sole()->alighted_at->equalTo($first->alighted_at))->toBeTrue();
    $esi()->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.run_id', $this->run->id)->assertJsonPath('trips.0.alighted_at', $first->alighted_at->toIso8601String());

    // Marked as not at the stop: that is for Help.
    $alight($ama)->assertStatus(422)->assertJsonPath('message', 'The mate marked you as not at your stop. If that is wrong, tell us under Help.');
});

it('says why Google would not trace a road', function () {
    config(['aveo.maps.google_key' => 'test-key']);
    Http::fake([GoogleRoutes::ENDPOINT => Http::response(['error' => ['code' => 403, 'message' => 'Routes API has not been used in project 1 before or it is disabled.']], 403)]);

    $this->artisan('network:trace-routes')->expectsOutputToContain('failed (Google said: Routes API has not been used in project 1 before or it is disabled.)');
    expect($this->network['route']->fresh()->path)->toBeNull();
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

    // The road driven so far, from both phones merged by time, ends at the van.
    phone($this->mate)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(2)]]);
    $trail = $this->getJson("/t/{$trip->public_id}/data")->json('trail');
    expect($trail)->toHaveCount(2)
        ->and($trail[1])->toBe([round($this->network['stops'][2]->latitude + 0.0002, 6), round($this->network['stops'][2]->longitude, 6)]);
    $this->get("/t/{$trip->public_id}?compact=1")->assertOk()->assertSee('compact', false);

    app(EndTrip::class)->handle($trip);
    $this->getJson("/t/{$trip->public_id}/data")->assertJsonPath('status', 'ended')->assertJsonPath('position', null)->assertJsonPath('trail', []);
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

it('shows a driver his commission and assessments, and nobody else', function () {
    $driver = $this->network['driver'];
    $admin = staff(Staff::SUPER_ADMIN);

    phone($this->driver)->getJson('/api/driver/v1/earnings')->assertOk()
        ->assertJsonPath('monthly_commission_label', 'GHS 300')->assertJsonPath('months', []);

    $september = app(RecordAssessment::class)->handle($driver, Carbon::parse('2026-09-01'), AssessmentOutcome::Met, $admin, 'On time every day.', 5000);
    app(MarkStatementPaid::class)->handle($september, $admin, 'MoMo 12345');
    app(RecordAssessment::class)->handle($driver, Carbon::parse('2026-10-01'), AssessmentOutcome::NotMet, $admin);

    $months = phone($this->driver)->getJson('/api/driver/v1/earnings')->assertOk()->json('months');

    expect($months)->toHaveCount(2)
        ->and($months[0])->toMatchArray(['month' => 'October 2026', 'assessment' => 'not_met', 'paid' => false, 'bonus_label' => null, 'total_label' => 'GHS 300'])
        ->and($months[1])->toMatchArray(['month' => 'September 2026', 'assessment' => 'met', 'paid' => true, 'bonus_label' => 'GHS 50', 'total_label' => 'GHS 350', 'assessment_notes' => 'On time every day.'])
        ->and(json_encode($months))->not->toContain('MoMo 12345'); // the payment reference is ours, not his

    phone($this->mate)->getJson('/api/driver/v1/earnings')->assertForbidden();
});

it('lets staff drive a pretend van from the console and see a rider\'s view, but never in production', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->operator, 'platform');
    $esi = $this->riders['Esi Boateng'];
    $page = TripSimulator::class;

    $this->get('/admin/trip-simulator')->assertOk()->assertSee('Start the trip')->assertSee('Esi Boateng');

    $sim = Livewire::test($page)->set('riderId', $esi->id);
    $sim->call('placeAtStop', $this->network['stops'][0]->id)->assertNotified('Start the trip first.');

    $sim->call('start');
    expect($sim->instance()->herView()['stops_away'])->toBe(3);

    $sim->call('placeAtStop', $this->network['stops'][0]->id);
    expect($sim->instance()->herView()['stops_away'])->toBe(2);

    // Halfway to the next stop: past this one, not yet at the next.
    $sim->call('placeAtStop', $this->network['stops'][1]->id, 50);
    expect($sim->instance()->herView()['stops_away'])->toBe(1);

    $sim->call('placeVan', $this->network['stops'][2]->latitude, $this->network['stops'][2]->longitude)->assertDispatched('aveo-refresh-map');
    expect($sim->instance()->herView()['stops_away'])->toBe(0)
        ->and(round(Trip::first()->latitude, 5))->toBe(round($this->network['stops'][2]->latitude, 5));

    $sim->call('mark', 'picked_up');
    expect(TripPickup::query()->sole()->rider_id)->toBe($esi->id);

    $sim->call('end')->call('restart');
    expect(Trip::count())->toBe(0)->and(TripPickup::count())->toBe(0);

    app()->detectEnvironment(fn () => 'production');
    expect($page::canAccess())->toBeFalse();
    app()->detectEnvironment(fn () => 'testing');
});

it('draws the road from Google once per route, and says minutes away in traffic when there is a key', function () {
    $esi = $this->riders['Esi Boateng']->account;
    $road = [[5.55, -0.30], [5.555, -0.297], [5.56, -0.29], [5.57, -0.28], [5.58, -0.27]];
    Http::fake(['routes.googleapis.com/*' => function ($request) use ($road) {
        return str_contains($request->header('X-Goog-FieldMask')[0], 'polyline')
            ? Http::response(['routes' => [['polyline' => ['encodedPolyline' => Polyline::encode($road)]]]])
            : Http::response(['routes' => [['duration' => '754s']]]);
    }]);

    // No key: nothing is asked, routes stay straight, and riders see stops, not minutes.
    $this->artisan('network:trace-routes')->expectsOutputToContain('no_key');
    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(0)]]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.path', null)->assertJsonPath('trips.0.minutes_away', null)->assertJsonPath('trips.0.stops_away', 2);
    Http::assertNothingSent();

    config(['aveo.maps.google_key' => 'test-key', 'aveo.maps.daily_limit' => 3]);
    $this->artisan('network:trace-routes')->expectsOutputToContain('traced');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && $request['travelMode'] === 'DRIVE' && $request['routingPreference'] === 'TRAFFIC_UNAWARE'
        && $request['origin']['location']['latLng']['latitude'] == $this->network['stops'][0]->latitude
        && count($request['intermediates']) === 2);

    // Three riders refreshing within a minute cost one call.
    foreach (range(1, 3) as $refresh) {
        phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.minutes_away', 13)->assertJsonPath('trips.0.path.4', [5.58, -0.27]);
    }
    expect(Http::recorded()->count())->toBe(2);
    $this->getJson('/t/'.Trip::first()->public_id.'/data?stop='.$this->network['stops'][2]->id)->assertJsonPath('minutes_away', 13);

    // Past the daily limit nothing more is asked, and the app falls back to stops.
    $this->travel(2)->minutes();
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(0)]]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.minutes_away', 13); // third call of three
    $this->travel(2)->minutes();
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(0)]]);
    phone($esi)->getJson('/api/rider/v1/trips')->assertJsonPath('trips.0.minutes_away', null)->assertJsonPath('trips.0.stops_away', 2);

    // Moving a stop's pin, or changing the stops, means a new road. The old one is dropped and traced again.
    Route::query()->first()->update(['path' => null]);
    Cache::flush();
    $this->network['stops'][1]->update(['latitude' => 5.5612]);
    expect(Route::query()->first()->path)->not->toBeNull();
});

it('lets staff trace a route\'s road from the console', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->operator, 'platform');
    $page = ManageRoutes::class;
    $route = $this->network['route'];

    Livewire::test($page)->callTableAction('trace', $route)->assertNotified('There is no Google Maps key on the server yet (GOOGLE_MAPS_API_KEY).');

    config(['aveo.maps.google_key' => 'test-key']);
    Http::fake(['routes.googleapis.com/*' => Http::response(['routes' => [['polyline' => ['encodedPolyline' => '_p~iF~ps|U_ulLnnqC']]]])]);
    Livewire::test($page)->callTableAction('trace', $route)->assertNotified('Done. Maps now draw this route along the streets.');

    expect($route->fresh()->roadPath())->toBe([[38.5, -120.2], [40.7, -120.95]]);
});

it('lets a rider say where she gets off, and the crew sees who is on board and who gets off where', function () {
    $mallam = $this->network['stops'][2];
    $seat = Seat::query()->where('rider_id', $this->riders['Ama Owusu']->id)->first();

    // Not a stop before hers, nor one off the route.
    phone($this->riders['Ama Owusu']->account)->putJson('/api/rider/v1/destination', ['seat_id' => $seat->id, 'stop_id' => $this->network['stops'][0]->id])
        ->assertStatus(422);
    phone($this->riders['Ama Owusu']->account)->putJson('/api/rider/v1/destination', ['seat_id' => $seat->id, 'stop_id' => $mallam->id])
        ->assertOk()->assertJsonPath('destination', 'Mallam');

    phone($this->riders['Ama Owusu']->account)->getJson('/api/rider/v1/home')
        ->assertJsonPath('seats.0.stop', 'Barrier')
        ->assertJsonPath('seats.0.gets_off', 'Mallam');

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Ama Owusu']->id, 'status' => 'picked_up']);

    $run = phone($this->mate)->getJson('/api/driver/v1/today')->json('runs.0');
    expect(array_column($run['on_board'], 'name'))->toBe(['Ama Owusu'])
        ->and($run['on_board'][0]['gets_off'])->toBe('Mallam')
        ->and(array_column($run['stops'][2]['getting_off'], 'name'))->toBe(['Ama Owusu'])
        ->and(array_column($run['stops'][3]['getting_off'], 'name'))->toBe(['Abena Darko', 'Esi Boateng']);

    phone($this->riders['Ama Owusu']->account)->getJson('/api/rider/v1/trips')
        ->assertJsonPath('trips.0.off_stop', 'Mallam')
        ->assertJsonPath('trips.0.stops_to_off', 2); // the van is at Barrier, two stops before Mallam

    // Once the van reaches Mallam she is off.
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(2)]]);
    expect(phone($this->mate)->getJson('/api/driver/v1/today')->json('runs.0.on_board'))->toBe([]);
});

it('tells riders and the crew when the van nears a stop, once per stop', function () {
    $mallam = $this->network['stops'][2];
    Seat::query()->where('rider_id', $this->riders['Ama Owusu']->id)->update(['destination_stop_id' => $mallam->id]);

    phone($this->driver)->postJson('/api/driver/v1/trip/start', ['run_id' => $this->run->id]);
    phone($this->mate)->postJson('/api/driver/v1/trip/pickups', ['run_id' => $this->run->id, 'rider_id' => $this->riders['Ama Owusu']->id, 'status' => 'picked_up']);

    // At Old Barrier, Mallam is about 1.5 km on: too far to say anything. Nobody gets on or off at Old Barrier itself.
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [ping(1)]]);
    expect(Message::query()->whereHas('rule', fn ($rule) => $rule->where('event_name', 'like', '%.approaching'))->count())->toBe(0);

    // Halfway to Mallam, about 800 metres off.
    $near = fn () => ['latitude' => 5.565, 'longitude' => -0.285, 'accuracy' => 10, 'recorded_at' => now()->toIso8601String()];
    phone($this->driver)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [$near()]]);
    phone($this->mate)->postJson('/api/driver/v1/trip/location', ['run_id' => $this->run->id, 'pings' => [$near()]]);

    $sent = fn (string $like) => Message::query()->where('body', 'like', $like)->orderBy('recipient_name')->pluck('recipient_name')->all();

    expect($sent('%nearly at Mallam%'))->toBe(['Abena Darko', 'Esi Boateng'])
        ->and($sent('%Mallam is next%'))->toBe(['Ama Owusu'])
        ->and($sent('Mallam next: 2 getting on, 1 getting off.'))->toBe(['Kofi Asante', $this->network['driver']->name])
        ->and(Message::query()->whereHas('rule', fn ($rule) => $rule->where('event_name', 'like', '%.approaching'))->pluck('channel')->unique()->values()->all())->toBe(['push'])
        ->and(Trip::first()->approached_stop_id)->toBe($mallam->id)
        // Sent once, though both phones were near.
        ->and(Message::query()->whereHas('rule', fn ($rule) => $rule->where('event_name', 'like', '%.approaching'))->count())->toBe(5);
});

it('picks a rider up where she goes, and drops her at her own stop, on the way back', function () {
    $back = run($this->network, ['direction' => 'return', 'departs_at' => '17:30']);
    $application = application($this->network, 'Efua Mensah', ['stop_id' => $this->network['stops'][0]->id]);
    app(ApproveApplicationWithSeats::class)->handle($application, [$this->run->id, $back->id], $this->operator);
    $plan = Plan::query()->where('duration_days', 30)->first();
    $plan->update(['trips_per_day' => 2]);
    app(ActivateSubscription::class)->handle($application->rider, $plan, $this->operator);

    $seat = Seat::query()->where('rider_id', $application->rider->id)->where('run_id', $back->id)->first();
    expect($seat->boardsAt())->toBe($this->network['stops'][3]->id)
        ->and($seat->getsOffAt())->toBe($this->network['stops'][0]->id)
        ->and($seat->pickupTime())->toBe('17:30');

    // Going to Mallam instead: she gets on there coming back.
    phone($application->rider->account)->putJson('/api/rider/v1/destination', ['seat_id' => $seat->id, 'stop_id' => $this->network['stops'][2]->id])->assertOk();
    expect($seat->fresh()->boardsAt())->toBe($this->network['stops'][2]->id);

    $stops = phone($this->driver)->getJson('/api/driver/v1/today')->json('runs.1.stops');
    expect($stops[0]['name'])->toBe('Circle')
        ->and(collect($stops)->firstWhere('name', 'Mallam')['riders'][0]['name'])->toBe('Efua Mensah')
        ->and(array_column(collect($stops)->firstWhere('name', 'Barrier')['getting_off'], 'name'))->toBe(['Efua Mensah']);
});
