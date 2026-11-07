<?php

namespace Database\Seeders;

use App\Support\Maps\Distance;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\ApproveDriver;
use Modules\Drivers\Actions\ApproveVehicle;
use Modules\Drivers\Actions\RecordVettingCheck;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Mate;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingItem;
use Modules\Network\Actions\SetRouteStops;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Riders\Actions\RejectApplication;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Enums\RejectionReason;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Actions\HoldGuestSeats;
use Modules\Subscriptions\Actions\RunDailyUpkeep;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Actions\SetDestination;
use Modules\Subscriptions\Actions\StartPlanPurchase;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Actions\MarkPickup;
use Modules\Trips\Actions\RecordAlighting;
use Modules\Trips\Actions\RecordPings;
use Modules\Trips\Actions\StartTrip;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;
use Modules\Trips\Queries\RidersOnTrip;
use RuntimeException;

/**
 * A believable month of Aveo, dated from the day it runs, so the demo never goes stale.
 *
 *   php artisan demo:reset          wipes the local database and runs this
 *
 * Two real Accra routes with pins, two drivers and their mates, morning and evening runs, and sixteen riders
 * at every stage: waiting, turned down, reserved and unpaid, paying, renewed, one payment failed then paid,
 * and one who let her plan lapse. Then it replays the last five weeks day by day: the daily upkeep at midnight,
 * applications and payments when they happened, and every trip from start to end with the van's position,
 * pickups, the odd missed rider and days riders said they would not ride.
 *
 * Today is replayed up to the present minute. Run it during a morning run and the van is on the road, live.
 *
 * It all goes through the same Actions the app and the console use, with the clock set to each moment,
 * so the event log, messages, payments and reports read as if it really happened. Nothing leaves the
 * machine: messages go to the log and payments to the fake gateway, whatever .env says.
 * It refuses to run in production. Names, numbers and pins are examples, not the pilot.
 */
class DemoSeeder extends Seeder
{
    /** How far back the story starts. Tests replay fewer days to stay quick. */
    public int $days = 35;

    /**
     * Build today's timetable around the moment the seeder runs, so there is always something to test:
     * the Kasoa van left about half an hour ago and is between stops, the Madina van leaves in about a quarter of an hour.
     * False keeps the fixed timetable, 06:00 and 06:15, which only has a van on the road early in the morning.
     */
    public bool $liveTimes = true;

    /**
     * Stops in travelling order: name, landmark, minutes from the start, latitude, longitude.
     * The pins are close to the real places. Move them in Network > Stops if one is off.
     */
    private const ROUTES = [
        'kasoa' => [
            'name' => 'Kasoa to Circle',
            'origin' => 'Kasoa',
            'destination' => 'Kwame Nkrumah Circle',
            'stops' => [
                ['Kasoa Old Market', 'By the overhead', 0, 5.5338, -0.4196],
                ['Tuba Junction', 'Opposite the filling station', 10, 5.5404, -0.3830],
                ['Weija Junction', 'Under the footbridge', 22, 5.5567, -0.3352],
                ['Old Barrier', 'SCC bus stop', 30, 5.5592, -0.3131],
                ['Mallam Junction', 'Near the market', 42, 5.5712, -0.2879],
                ['Kaneshie First Light', 'By the pedestrian bridge', 58, 5.5690, -0.2389],
                ['Circle', 'Odo Rise', 70, 5.5704, -0.2156],
            ],
        ],
        'madina' => [
            'name' => 'Madina to Accra Central',
            'origin' => 'Madina',
            'destination' => 'Accra Central',
            'stops' => [
                ['Madina Zongo Junction', 'Opposite the mosque', 0, 5.6700, -0.1656],
                ['Atomic Junction', 'At the lights', 8, 5.6635, -0.1810],
                ['Okponglo', 'UPSA bus stop', 15, 5.6440, -0.1745],
                ['Shiashie', 'By the overpass', 25, 5.6150, -0.1780],
                ['37 Station', 'Lorry station gate', 32, 5.5930, -0.1830],
                ['Ridge', 'Ridge roundabout', 42, 5.5655, -0.1990],
                ['Accra Central', 'Makola, Kinbu Road', 52, 5.5480, -0.2068],
            ],
        ],
    ];

    /**
     * The buses: two on each route, one Aveo's and one a partner's, the second leaving a little after the first.
     * Each van says whether riders may hold seats for people with them, and how those people pay.
     */
    private const BUSES = [
        'kasoa' => ['route' => 'kasoa', 'driver' => ['Kwame Mensah', '0245550001'], 'mate' => ['Kofi Asante', '0245550002'],
            'van' => ['GT 1234-22', 'Toyota Hiace', 'White', 15], 'owner' => null, 'extra' => 'fare', 'later' => 0],
        'kasoa2' => ['route' => 'kasoa', 'driver' => ['Kwabena Owusu', '0245550005'], 'mate' => ['Yaw Darko', '0245550006'],
            'van' => ['GE 4521-21', 'Mercedes Sprinter', 'Blue', 18], 'owner' => 'Asafo Transport', 'extra' => 'plan', 'later' => 20],
        'madina' => ['route' => 'madina', 'driver' => ['Yaw Boakye', '0245550003'], 'mate' => ['Kojo Antwi', '0245550004'],
            'van' => ['GR 5678-23', 'Nissan Urvan', 'Silver', 15], 'owner' => null, 'extra' => 'off', 'later' => 0],
        'madina2' => ['route' => 'madina', 'driver' => ['Ebo Quansah', '0245550007'], 'mate' => ['Fiifi Aidoo', '0245550008'],
            'van' => ['GN 7788-20', 'Toyota Hiace', 'Grey', 15], 'owner' => 'Kumi Brothers', 'extra' => 'fare', 'later' => 30],
    ];

    /**
     * The riders and what happened to each, in days before today.
     * name, phone, bus, stop index, run (am or pm), and the steps: applied, approved, paid (plan, and how many people
     * when others travel on her plan), failed, rejected, and seats held for people paying the fare (day from today => how many).
     */
    private const RIDERS = [
        ['Ama Owusu', '0245550101', 'kasoa', 2, 'am', ['apply' => 34, 'approve' => 34, 'pay' => [[33, '3 months']]]],
        ['Esi Boateng', '0245550102', 'kasoa', 4, 'am', ['apply' => 34, 'approve' => 33, 'pay' => [[33, '1 month'], [4, '1 month']], 'guests' => [-6 => 1, 0 => 2, 2 => 1]]],
        ['Abena Darko', '0245550103', 'kasoa', 4, 'am', ['apply' => 30, 'approve' => 29, 'pay' => [[28, '1 month']]]],
        ['Yaa Asantewaa', '0245550104', 'kasoa', 5, 'pm', ['apply' => 23, 'approve' => 22, 'pay' => [[21, '2 weeks']]]],
        ['Akosua Frimpong', '0245550105', 'kasoa2', 0, 'am', ['apply' => 14, 'approve' => 13, 'pay' => [[12, '1 month']]]],
        ['Adwoa Sarpong', '0245550106', 'kasoa', 3, 'pm', ['apply' => 12, 'approve' => 11, 'pay' => [[10, '1 month']]]],
        ['Efua Mensah', '0245550107', 'kasoa2', 2, 'am', ['apply' => 8, 'approve' => 7, 'pay' => [[6, '2 weeks', 3]]]],
        ['Afia Kyei', '0245550108', 'kasoa2', 5, 'am', ['apply' => 3, 'approve' => 2]],
        ['Akua Addo', '0245550109', 'kasoa', 4, 'am', ['apply' => 1]],
        ['Serwaa Ofori', '0245550110', 'kasoa', 0, 'pm', ['apply' => 16, 'reject' => 15]],
        ['Adjoa Quaye', '0245550111', 'madina', 0, 'am', ['apply' => 27, 'approve' => 26, 'pay' => [[25, '1 month']]]],
        ['Naa Lamptey', '0245550112', 'madina', 1, 'am', ['apply' => 24, 'approve' => 23, 'pay' => [[22, '3 months']]]],
        ['Dede Tetteh', '0245550113', 'madina', 4, 'pm', ['apply' => 20, 'approve' => 19, 'pay' => [[18, '1 month']]]],
        ['Korkor Ashong', '0245550114', 'madina2', 3, 'am', ['apply' => 11, 'approve' => 10, 'pay' => [[9, '2 weeks']], 'guests' => [0 => 1]]],
        ['Ewurabena Hagan', '0245550115', 'madina', 2, 'am', ['apply' => 7, 'approve' => 6, 'fail' => 5, 'pay' => [[4, '1 month']]]],
        ['Maame Badu', '0245550116', 'madina2', 3, 'am', ['apply' => 0]],
    ];

    /**
     * Riders who don't go to the end of the route: the stop each goes to, by its place on the route. Going out they
     * get off there. Adwoa and Dede ride back in the evening, so they get on there and get off at their own stop.
     */
    private const DESTINATIONS = ['Ama Owusu' => 5, 'Abena Darko' => 5, 'Adwoa Sarpong' => 5, 'Naa Lamptey' => 4, 'Dede Tetteh' => 5, 'Ewurabena Hagan' => 5];

    private Staff $admin;

    /** @var array<string, Route> */
    private array $routes = [];

    /** @var array<string, Run> keyed "kasoa.am" */
    private array $runs = [];

    /** @var array<string, Account> keyed by run, the phone that sends the van's position */
    private array $crew = [];

    /** @var array<string, RiderApplication> keyed by rider name */
    private array $applications = [];

    private int $skipped = 0;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        $this->call(DatabaseSeeder::class);

        if (Route::query()->exists()) {
            $this->command?->warn('There is already a route, so the demo was not added. Run `php artisan demo:reset` to start again from today.');

            return;
        }

        $this->quiet();

        // A clock someone has already fixed, as a test does, is put back when the replay ends.
        $fixed = Carbon::getTestNow();
        $real = CarbonImmutable::now();
        $today = $real->startOfDay();
        $this->admin = Staff::query()->firstOrFail();

        try {
            $this->at($today->subDays($this->days)->setTime(8, 0));
            $this->network($real);

            for ($ago = $this->days - 1; $ago >= 0; $ago--) {
                $this->day($today->subDays($ago), $real);
            }
        } finally {
            Carbon::setTestNow($fixed);
            CarbonImmutable::setTestNow($fixed);
        }

        // Aveo Ox: three weeks of parcels, and a run out on the road now.
        (new OxDemoSeeder)->run($this->admin, $real, min(21, $this->days));

        $this->command?->info(sprintf(
            'Demo added: 2 routes, 4 buses on %d trips a day, %d riders, %d trips replayed over %d days. %s',
            count($this->runs),
            count(self::RIDERS),
            Trip::query()->count(),
            $this->days,
            $this->skipped > 0 ? "{$this->skipped} replayed steps were refused by the rules and skipped." : '',
        ));

        foreach ($this->today() as $line) {
            $this->command?->line($line);
        }

        $this->command?->info(OxDemoSeeder::summary());
    }

    /** Nothing leaves the machine, and everything a queue would do later happens at once. */
    private function quiet(): void
    {
        config([
            'queue.default' => 'sync',
            'messaging.channels.sms.driver' => 'log',
            'messaging.channels.whatsapp.driver' => 'null',
            'messaging.channels.push.driver' => 'null',
            'payments.driver' => 'fake',
            'payments.fake.settle_after_seconds' => -1,
        ]);
    }

    /**
     * Today's runs in a line each, with where they stand now.
     *
     * @return list<string>
     */
    public function today(): array
    {
        $now = CarbonImmutable::now();
        $trips = Trip::query()->where('date', $now->toDateString())->get()->keyBy('run_id');
        $where = fn (Run $run) => $run->direction->value === 'outbound' ? $run->route->destination : $run->route->origin_area;

        return Run::query()->where('is_active', true)->with(['route', 'vehicle', 'driver'])->get()
            ->filter(fn (Run $run) => $run->runsOn($now))
            ->sortBy(fn (Run $run) => $run->departureTime())
            ->groupBy('vehicle_id')
            ->map(function ($runs) use ($trips, $now, $where) {
                $first = $runs->first();
                $who = "{$first->vehicle->registration_number}, {$first->driver->name}".($first->vehicle->owner_name ? " ({$first->vehicle->owner_name})" : '');
                $onRoad = $runs->first(fn (Run $run) => $trips->get($run->id)?->isRunning());
                $next = $runs->first(fn (Run $run) => ! $trips->has($run->id) && $this->startOf($run, $now)->gt($now));
                $done = $runs->filter(fn (Run $run) => $trips->get($run->id)?->isRunning() === false)->count();

                return "  {$first->route->name}, {$who}: "
                    .($onRoad ? "ON THE ROAD to {$where($onRoad)}, left ".$trips[$onRoad->id]->started_at->format('H:i').'. ' : '')
                    .($next ? "Next {$next->departureTime()} to {$where($next)}. " : 'No more today. ')
                    ."{$done} of {$runs->count()} trips done.";
            })
            ->prepend('Today:')
            ->values()
            ->all();
    }

    /**
     * Drives today's vans in real time, for testing the rider, driver and mate screens as if on the road:
     * a van sets off when it is due, sends its position every few seconds along the road, the mate marks the riders
     * at each stop as it reaches them, and the trip ends on arrival. Stops when no van is left to drive today.
     *
     * @param  callable(string): void  $say
     */
    public function drive(callable $say, int $everySeconds = 10): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo driver must not run in production.');
        }

        $this->quiet();

        while ($this->tick(CarbonImmutable::now(), $say) > 0) {
            sleep($everySeconds);
        }

        $say('No van left to drive in the next three hours. Stopping.');
    }

    /** Stops each run has reached while driving, so riders there are marked once. @var array<int, array<int, true>> */
    private array $driven = [];

    /**
     * One step of driving: start what is due, move what is on the road, end what has arrived.
     *
     * @param  (callable(string): void)|null  $say
     * @return int how many of today's runs are still to drive, now or within three hours
     */
    public function tick(CarbonImmutable $now, ?callable $say = null): int
    {
        $say ??= fn (string $line) => null;
        $runs = Run::query()->where('is_active', true)->with(['route.stops', 'driver'])->get()->filter(fn (Run $run) => $run->runsOn($now));
        $left = 0;

        foreach ($runs as $run) {
            $start = $this->startOf($run, $now);
            $trip = Trip::query()->where('run_id', $run->id)->where('date', $now->toDateString())->first();

            if ($trip?->isRunning() === false || $start->gt($now->addHours(3))) {
                continue; // done today, or too far off to wait for
            }

            $left++;

            if ($start->gt($now)) {
                continue; // not due yet
            }

            $mate = Mate::query()->where('driver_id', $run->driver_id)->where('is_active', true)->first()?->account ?? $run->driver->account;

            if ($trip === null) {
                $trip = app(StartTrip::class)->handle($run, $run->driver->account);
                $say("{$run->route->name}: set off.");
            }

            $stops = $this->timeline($trip, $start);
            $end = $stops->last()['at']->addMinutes(3);

            if ($now->gte($end)) {
                $this->arrive($trip, $end);
                app(EndTrip::class)->handle($trip->fresh(), $run->driver->account);
                $say("{$run->route->name}: arrived, trip ended.");

                continue;
            }

            [$latitude, $longitude] = $this->position($stops->all(), $now, $this->road($run));
            app(RecordPings::class)->handle($trip, $mate, [['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 10, 'recorded_at' => $now->toIso8601String()]]);

            foreach ($stops as $stop) {
                if ($stop['at']->lte($now) && ! isset($this->driven[$run->id][$stop['stop']->id])) {
                    $this->driven[$run->id][$stop['stop']->id] = true;
                    $this->pickups($trip->fresh(), $stop['stop']->id, $mate, $now);
                    $say("{$run->route->name}: at {$stop['stop']->name}.");
                }
            }
        }

        return $left;
    }

    /** Routes with pinned stops, two approved drivers with mates and vans, and a morning and an evening run on each route. */
    private function network(CarbonImmutable $real): void
    {
        foreach (self::ROUTES as $key => $plan) {
            $route = Route::query()->create(['name' => $plan['name'], 'origin_area' => $plan['origin'], 'destination' => $plan['destination']]);
            $stops = collect($plan['stops'])->map(fn (array $stop) => [
                'stop_id' => Stop::query()->create(['name' => $stop[0], 'landmark' => $stop[1], 'area' => $plan['origin'], 'latitude' => $stop[3], 'longitude' => $stop[4]])->id,
                'minutes_from_start' => $stop[2],
            ]);
            // With GOOGLE_MAPS_API_KEY set, this also traces the road once, so the maps follow it.
            app(SetRouteStops::class)->handle($route, $stops->all());
            $this->routes[$key] = $route;
        }

        $times = $this->timetable($real);

        foreach (self::BUSES as $key => $bus) {
            $plan = $times[$key];
            $driver = app(RegisterDriver::class)->handle(['name' => $bus['driver'][0], 'phone' => $bus['driver'][1], 'licence_number' => 'DEMO-'.strtoupper($key), 'monthly_commission' => 30000]);
            $mate = app(RegisterMate::class)->handle($driver, $bus['mate'][0], $bus['mate'][1]);
            [$plate, $make, $colour, $seats] = $bus['van'];
            $vehicle = Vehicle::query()->create([
                'driver_id' => $driver->id, 'registration_number' => $plate, 'make' => $make, 'colour' => $colour, 'seats_total' => $seats,
                'ownership' => $bus['owner'] ? 'partner' : 'own', 'owner_name' => $bus['owner'], 'extra_seats' => $bus['extra'],
            ]);
            $this->approve($driver, $vehicle);

            foreach ($plan['trips'] as $k => [$direction, $departs]) {
                $run = app(SaveRun::class)->handle([
                    'route_id' => $this->routes[$bus['route']]->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'direction' => $direction,
                    'departs_at' => $departs, 'days_of_week' => $plan['days'], 'seats_held' => $seats - 3,
                ]);
                $slot = match ($k) {
                    $plan['am'] => 'am',
                    $plan['pm'] => 'pm',
                    default => "trip{$k}",
                };
                $this->runs["{$key}.{$slot}"] = $run;
                // The mate's phone sends the position, as it would on the road.
                $this->crew["{$key}.{$slot}"] = $mate->account;
            }
        }
    }

    /**
     * Each bus's days and its trips: back and forth along its route all day, from about 05:00 to 21:30, a few minutes'
     * turnaround at each end. The second bus on a route runs 20 to 30 minutes behind the first.
     *
     * Live, today is built around the moment the seeder runs: one Kasoa trip left about 30 minutes ago and is halfway,
     * one Madina trip leaves in about a quarter of an hour. Fixed, the day starts at 05:30 and 05:45 like a pilot would.
     *
     * `am` and `pm` name the two trips the riders hold seats on: the one at the anchor, to the city, and the trip back
     * about eleven hours later.
     *
     * @return array<string, array{days: list<int>, trips: array<int, array{0: string, 1: string}>, am: int, pm: int}>
     */
    private function timetable(CarbonImmutable $real): array
    {
        $clock = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        $now = $real->hour * 60 + $real->minute;
        $leg = ['kasoa' => 75, 'madina' => 60]; // the drive one way, plus the turnaround
        $anchor = $this->liveTimes
            ? ['kasoa' => intdiv($now - 30, 5) * 5, 'madina' => intdiv($now + 19, 5) * 5]
            : ['kasoa' => 5 * 60 + 30, 'madina' => 5 * 60 + 45];
        $days = $this->liveTimes ? ['kasoa' => [1, 2, 3, 4, 5, 6, 7], 'madina' => [1, 2, 3, 4, 5, 6, 7]] : ['kasoa' => [1, 2, 3, 4, 5, 6], 'madina' => [1, 2, 3, 4, 5]];

        return collect(self::BUSES)->map(function (array $bus) use ($clock, $leg, $anchor, $days) {
            $every = $leg[$bus['route']];
            $first = $anchor[$bus['route']] + $bus['later'];
            $trips = [];

            // Trip k leaves k legs after the anchor: to the city when k is even, back when it is odd.
            // A bus starts its day at home and drives to the city first, so the first trip is always an even one.
            $from = -intdiv(max(0, $first - 5 * 60), $every);
            $from += abs($from) % 2;

            for ($k = $from; $first + $k * $every <= 21 * 60 + 30; $k++) {
                $departs = $first + $k * $every;

                if ($departs >= 4 * 60 + 30) {
                    $trips[$k] = [abs($k) % 2 === 0 ? 'outbound' : 'return', $clock($departs)];
                }
            }

            // The anchor trip, or the first of the day if the anchor fell outside it; the way back about eleven hours on.
            $am = isset($trips[0]) ? 0 : (int) collect($trips)->filter(fn ($trip) => $trip[0] === 'outbound')->keys()->first();
            $pm = (int) collect($trips)->filter(fn ($trip, $k) => $trip[0] === 'return' && $k > $am)
                ->keys()->sortBy(fn (int $k) => abs(($k - $am) * $every - 660))->first();

            return ['days' => $days[$bus['route']], 'trips' => $trips, 'am' => $am, 'pm' => $pm];
        })->all();
    }

    private function approve(Driver $driver, Vehicle $vehicle): void
    {
        foreach ([$driver, $vehicle] as $subject) {
            VettingItem::query()->for($subject)->get()
                ->each(fn (VettingItem $item) => app(RecordVettingCheck::class)->handle($subject, $item, VettingResult::Passed, $this->admin));
        }

        app(ApproveDriver::class)->handle($driver, $this->admin);
        app(ApproveVehicle::class)->handle($vehicle);
    }

    /** One day, in the order things happen: upkeep at midnight, the morning runs, the office's work, the evening runs, notices given at night. */
    private function day(CarbonImmutable $date, CarbonImmutable $real): void
    {
        if ($date->setTime(0, 10)->lte($real)) {
            $this->at($date->setTime(0, 10));
            app(RunDailyUpkeep::class)->handle($date);
        }

        $steps = collect();

        // Every trip today and yesterday. Before that only the trips riders hold seats on, which is all the history
        // anyone looks at, so a month replays in under a minute.
        $recent = $date->gte($real->startOfDay()->subDay());

        foreach ($this->runs as $key => $run) {
            if ($run->runsOn($date) && ($recent || str_ends_with($key, '.am') || str_ends_with($key, '.pm'))) {
                $start = $this->startOf($run, $date);
                $steps->push([$start, fn () => $this->trip($key, $run, $start, $real)]);
            }
        }

        foreach (self::RIDERS as $index => [$name, $phone, $bus, $stopIndex, $slot, $story]) {
            $ago = (int) $date->diffInDays($real->startOfDay());
            $office = $date->setTime(9 + $index % 6, 5 * $index % 60);

            foreach ($story as $step => $when) {
                if ($step === 'guests') {
                    continue;
                }

                foreach ($step === 'pay' ? $when : [[$when, null]] as $entry) {
                    [$day, $plan, $seats] = [...$entry, null, null];

                    if ($day === $ago) {
                        $at = match ($step) {
                            'apply' => $date->setTime(7 + $index % 12, 7 * $index % 60),
                            'pay', 'fail' => $office->addHours(2),
                            default => $office,
                        };
                        $steps->push([$at, fn () => $this->story($step, $name, $phone, $bus, $stopIndex, $slot, $plan, $seats)]);
                    }
                }
            }

            // Seats held for people paying the fare: the evening before for days up to today, and early today for days ahead.
            foreach ($story['guests'] ?? [] as $fromToday => $people) {
                if ($fromToday <= 0 && $fromToday === 1 - $ago) {
                    $steps->push([$date->setTime(20, 15), fn () => $this->guests($name, $bus, $slot, $date->addDay(), $people)]);
                } elseif ($fromToday > 0 && $ago === 0) {
                    $steps->push([$date->setTime(0, 20), fn () => $this->guests($name, $bus, $slot, $date->addDays($fromToday), $people)]);
                }
            }

            // Now and then a rider says the evening before that she won't ride tomorrow.
            if (isset($story['pay']) && ($index * 7 + $date->dayOfYear) % 17 === 0) {
                $steps->push([$date->setTime(20, 30), fn () => $this->notice($name, $date->addDay())]);
            }
        }

        $steps->sortBy(fn (array $step) => $step[0])
            ->filter(fn (array $step) => $step[0]->lte($real))
            ->each(function (array $step) {
                $this->at($step[0]);
                $this->attempt($step[1]);
            });
    }

    private function story(string $step, string $name, string $phone, string $bus, int $stopIndex, string $slot, ?string $plan, ?int $seats = null): void
    {
        $route = $this->routes[self::BUSES[$bus]['route']];

        if ($step === 'apply') {
            $account = Account::query()->create(['phone' => '+233'.substr($phone, 1), 'name' => $name]);
            $account->assignRole('rider');
            $stop = $route->stops()->get()[$stopIndex];
            $times = $slot === 'am' ? ['morning_time' => substr($this->runs["{$bus}.am"]->departs_at, 0, 5)] : ['evening_time' => substr($this->runs["{$bus}.pm"]->departs_at, 0, 5)];
            $this->applications[$name] = app(SubmitApplication::class)->handle($account, ['name' => $name, 'route_id' => $route->id, 'stop_id' => $stop->id, ...$times]);

            return;
        }

        // A shortened replay can start after a rider applied. Her later steps have nothing to act on.
        $application = $this->applications[$name] ?? null;

        if ($application === null) {
            return;
        }

        match ($step) {
            'approve' => $this->approveRider($application, $name, $this->runs["{$bus}.{$slot}"], $route),
            'reject' => app(RejectApplication::class)->handle($application, $this->admin, RejectionReason::NoCapacity, 'The evening run is full for now. We will tell you when a seat frees up.'),
            'fail' => $this->pay($application, $phone, '1 month', paid: false),
            'pay' => $this->pay($application, $phone, (string) $plan, paid: true, seats: $seats),
        };
    }

    private function approveRider(RiderApplication $application, string $name, Run $run, Route $route): void
    {
        app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], $this->admin);

        if (isset(self::DESTINATIONS[$name])) {
            $seat = Seat::query()->where('application_id', $application->id)->firstOrFail();
            app(SetDestination::class)->handle($seat, $route->stops()->get()[self::DESTINATIONS[$name]]->id);
        }
    }

    /** A mobile money payment through the fake gateway, settled the way Hubtel's callback would settle it. */
    private function pay(RiderApplication $application, string $phone, string $planName, bool $paid, ?int $seats = null): void
    {
        $plan = Plan::query()->where('name', $planName)->firstOrFail();
        $purchase = app(StartPlanPurchase::class)->handle($application->rider()->firstOrFail(), $plan, $phone, 'mtn', seats: $seats);

        $this->at(CarbonImmutable::now()->addSeconds(40));
        app(ApplyOutcome::class)->handle(
            $purchase->payment()->firstOrFail(),
            $paid,
            $paid ? 'DEMO-'.$purchase->id : null,
            'demo',
            null,
            $paid ? null : 'Not enough money in the wallet.',
        );
    }

    private function guests(string $name, string $bus, string $slot, CarbonImmutable $date, int $people): void
    {
        $rider = isset($this->applications[$name]) ? $this->applications[$name]->rider()->first() : null;
        $seat = $rider ? Seat::query()->where('rider_id', $rider->id)->where('run_id', $this->runs["{$bus}.{$slot}"]->id)->first() : null;

        if ($seat !== null) {
            app(HoldGuestSeats::class)->handle($seat, $date, $people);
        }
    }

    private function notice(string $name, CarbonImmutable $date): void
    {
        // Only riders who have applied by now can give notice. The rules decide the rest.
        $rider = isset($this->applications[$name]) ? $this->applications[$name]->rider()->first() : null;

        if ($rider === null) {
            return;
        }

        app(GiveAbsenceNotice::class)->handle($rider, $date, CarbonImmutable::now());
    }

    /**
     * One trip from start to end, as the crew's phones would report it: a position every three minutes along
     * the stops, each rider marked at her stop, and the trip ended at the last stop. A trip still under way at
     * the present minute is left running.
     */
    private function trip(string $key, Run $run, CarbonImmutable $start, CarbonImmutable $real): void
    {
        $trip = app(StartTrip::class)->handle($run, $run->driver->account);
        $phone = $this->crew[$key];
        $stops = $this->timeline($trip, $start);
        $road = $this->road($run);
        $end = $stops->last()['at']->addMinutes(3);
        $marked = [];

        for ($t = $start; $t->lte($end); $t = $t->addMinutes(3)) {
            if ($t->gt($real)) {
                return;
            }

            $this->at($t);
            [$latitude, $longitude] = $this->position($stops->all(), $t, $road);
            app(RecordPings::class)->handle($trip, $phone, [['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 12, 'recorded_at' => $t->toIso8601String()]]);

            foreach ($stops as $index => $stop) {
                if ($stop['at']->lte($t) && ! isset($marked[$index])) {
                    $marked[$index] = true;
                    $this->pickups($trip->fresh(), $stop['stop']->id, $phone, $t);
                }
            }
        }

        $this->at($end->subMinute());
        $this->arrive($trip, $end);
        $this->at($end);
        app(EndTrip::class)->handle($trip->fresh(), $run->driver->account);
    }

    /** About half the riders tap "I've got down" when they arrive. */
    private function arrive(Trip $trip, CarbonImmutable $end): void
    {
        $trip->fresh()->pickups->where('status', PickupStatus::PickedUp)
            ->filter(fn (TripPickup $pickup) => ($pickup->rider_id + $end->dayOfYear) % 2 === 0)
            ->each(fn (TripPickup $pickup) => $this->attempt(fn () => app(RecordAlighting::class)->handle($trip->fresh(), $pickup->rider_id, $pickup->stop_id)));
    }

    /** When a run's trip starts on a day: a few minutes before it is due at the first stop. */
    private function startOf(Run $run, CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfDay()->setTimeFromTimeString($run->departs_at)->subMinutes(5 - $this->wobble("run{$run->id}", $date, 4));
    }

    /**
     * When the van reaches each stop on the day: the timetable, give or take traffic.
     *
     * @return Collection<int, array{stop: Stop, at: CarbonImmutable}>
     */
    private function timeline(Trip $trip, CarbonImmutable $start): Collection
    {
        $run = $trip->run;
        $late = $this->wobble("run{$run->id}", $start, 6);

        return $trip->stopsInOrder()->values()->map(fn (Stop $stop, int $index) => [
            'stop' => $stop,
            'at' => $start->startOfDay()->setTimeFromTimeString($run->timeAtStop($stop->id) ?? $run->departs_at)->addMinutes($index === 0 ? 0 : $late),
        ]);
    }

    /**
     * The road the van follows, in the direction it travels. Null until the route has been traced.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function road(Run $run): ?array
    {
        $path = $run->route->fresh()->roadPath();

        return $path && $run->direction === Direction::Return ? array_reverse($path) : $path;
    }

    /**
     * At a stop: about half the riders getting off tap "I've got down", and the mate marks each rider waiting there:
     * on board, or now and then not there.
     */
    private function pickups(Trip $trip, int $stopId, Account $mate, CarbonImmutable $at): void
    {
        $stop = app(RidersOnTrip::class)->handle($trip->run, $trip->load('pickups'))->firstWhere('stop_id', $stopId);
        $riding = $trip->pickups->where('status', PickupStatus::PickedUp)->whereNull('alighted_at')->keyBy('rider_id');

        foreach ($stop['getting_off'] ?? [] as $rider) {
            if (isset($riding[$rider['rider_id']]) && ($rider['rider_id'] + $at->dayOfYear) % 2 === 0) {
                $this->attempt(fn () => app(RecordAlighting::class)->handle($trip, $rider['rider_id'], $stopId));
            }
        }

        foreach ($stop['riders'] ?? [] as $rider) {
            if ($rider['absent'] || $rider['pickup'] !== null) {
                continue;
            }

            $missed = ($rider['rider_id'] * 3 + $at->dayOfYear) % 23 === 0;
            $this->attempt(fn () => app(MarkPickup::class)->handle($trip, $rider['rider_id'], $missed ? PickupStatus::Missed : PickupStatus::PickedUp, $mate));
        }
    }

    /**
     * Where the van is at a moment, between the stop it last passed and the next, with a little drift so the trail looks driven.
     *
     * Once the route is traced it moves along the road itself, not straight from stop to stop.
     *
     * @param  list<array{stop: Stop, at: CarbonImmutable}>  $stops
     * @param  list<array{0: float, 1: float}>|null  $road
     * @return array{0: float, 1: float}
     */
    private function position(array $stops, CarbonImmutable $t, ?array $road = null): array
    {
        $next = collect($stops)->search(fn (array $stop) => $stop['at']->gt($t));

        if ($next === false || $next === 0) {
            $stop = $stops[$next === 0 ? 0 : count($stops) - 1]['stop'];

            return [$stop->latitude, $stop->longitude];
        }

        [$a, $b] = [$stops[$next - 1], $stops[$next]];
        $share = $a['at']->diffInSeconds($t) / max(1, $a['at']->diffInSeconds($b['at']));

        if ($road !== null && count($road) > 1) {
            $from = $this->nearest($road, $a['stop']);
            $to = max($from, $this->nearest($road, $b['stop']));

            return $road[(int) round($from + ($to - $from) * $share)];
        }

        $drift = sin($t->minute) * 0.0004;

        return [
            round($a['stop']->latitude + ($b['stop']->latitude - $a['stop']->latitude) * $share + $drift, 6),
            round($a['stop']->longitude + ($b['stop']->longitude - $a['stop']->longitude) * $share, 6),
        ];
    }

    /** @param  list<array{0: float, 1: float}>  $road */
    private function nearest(array $road, Stop $stop): int
    {
        $best = 0;

        foreach ($road as $index => [$latitude, $longitude]) {
            if (Distance::metres($latitude, $longitude, $stop->latitude, $stop->longitude) < Distance::metres($road[$best][0], $road[$best][1], $stop->latitude, $stop->longitude)) {
                $best = $index;
            }
        }

        return $best;
    }

    /** A steady "random" number of minutes for a run on a day, so two runs of the seeder tell the same story. */
    private function wobble(string $key, CarbonImmutable $date, int $max): int
    {
        return crc32($key.$date->toDateString()) % ($max + 1);
    }

    /** Sets the clock for everything that follows. */
    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    /** A replayed step the rules turn down, such as a notice given too late, is skipped like the app would refuse it. */
    private function attempt(callable $step): void
    {
        try {
            $step();
        } catch (DomainException) {
            $this->skipped++;
        }
    }
}
