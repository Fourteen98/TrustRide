<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Drivers\Models\Vehicle;
use Modules\Messaging\Models\Message;
use Modules\Network\Models\Stop;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Models\GuestSeats;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Models\LocationPing;
use Modules\Trips\Models\Trip;
use Modules\Trips\Models\TripPickup;

/*
 * The demo replays real days through the real Actions, so it breaks when a rule changes in a way the
 * story no longer fits. Ten days keeps the test quick; `php artisan demo:reset` replays thirty-five.
 */
it('replays the last days up to the present minute', function () {
    Carbon::setTestNow('2026-10-22 06:40:00'); // a Thursday, while the morning vans are on the road

    $this->app->instance(DemoSeeder::class, tap(new DemoSeeder, function (DemoSeeder $seeder) {
        $seeder->days = 10;
        $seeder->liveTimes = false; // the fixed 06:00 and 06:15 timetable
    }));
    $this->seed(DemoSeeder::class);

    expect(Trip::query()->where('status', TripStatus::Ended)->count())->toBeGreaterThan(20)
        ->and(Trip::query()->where('status', TripStatus::Running)->where('date', '2026-10-22')->count())->toBeGreaterThanOrEqual(3)
        ->and(TripPickup::query()->where('status', 'picked_up')->count())->toBeGreaterThan(5)
        ->and(Payment::query()->where('status', 'failed')->count())->toBe(1)
        ->and(Subscription::query()->covering(today())->count())->toBeGreaterThan(1)
        ->and(Message::query()->where('channel', 'sms')->exists())->toBeTrue();

    // Everything happened in the past or now, never later today.
    expect(Trip::query()->max('started_at'))->toBeLessThanOrEqual(now()->toDateTimeString());

    // A second run leaves the data alone.
    $trips = Trip::query()->count();
    $this->seed(DemoSeeder::class);
    expect(Trip::query()->count())->toBe($trips);
});

it('builds today around the moment it runs, so a van is always on the road and another about to leave', function () {
    Carbon::setTestNow('2026-10-24 14:07:00'); // a Saturday afternoon: the fixed timetable would have nothing to show

    $this->app->instance(DemoSeeder::class, tap(new DemoSeeder, fn (DemoSeeder $seeder) => $seeder->days = 12));
    $this->seed(DemoSeeder::class);

    $today = Trip::query()->where('date', '2026-10-24')->with('run.route')->get();
    // Buses shuttle all day. Both Kasoa buses are on the road into town, and a Madina trip to town leaves within half an hour.
    $running = $today->where('status', TripStatus::Running);
    expect($running->where('run.route.name', 'Kasoa to Circle')->where('run.direction.value', 'outbound'))->toHaveCount(2)
        ->and(Run::query()->count())->toBeGreaterThan(40);

    $soon = Run::query()->whereHas('route', fn ($route) => $route->where('name', 'Madina to Accra Central'))->where('direction', 'outbound')
        ->whereBetween('departs_at', ['14:10', '14:40'])->pluck('id');
    expect($soon)->not->toBeEmpty()
        ->and(Trip::query()->where('date', '2026-10-24')->whereIn('run_id', $soon)->exists())->toBeFalse();

    // People travelling with riders: one held today with Korkor, paying the fare; three on Efua's plan on the partner's bus.
    expect((int) GuestSeats::query()->where('date', '2026-10-24')->sum('seats'))->toBeGreaterThanOrEqual(1)
        ->and(Seat::query()->where('party', 3)->count())->toBe(1)
        ->and(Vehicle::query()->where('ownership', 'partner')->count())->toBe(2);

    // Some riders get off before the end of the route: Ewurabena at Ridge going out, Adwoa at her own stop coming back.
    // And people are told as the van nears their stop.
    $midway = Stop::query()->whereIn('name', ['Ridge', 'Old Barrier'])->pluck('id');
    expect(TripPickup::query()->whereIn('alighted_stop_id', $midway)->exists())->toBeTrue()
        ->and(Message::query()->whereHas('rule', fn ($rule) => $rule->where('event_name', 'stop.approaching'))->exists())->toBeTrue()
        ->and(Message::query()->whereHas('rule', fn ($rule) => $rule->where('event_name', 'crew.approaching'))->exists())->toBeTrue();

    // Ox: a run out on the road now with a parcel delivered by its code, and parcels waiting for an operator to batch.
    $out = DeliveryRun::query()->where('status', 'out')->whereDate('date', '2026-10-24')->first();
    expect($out?->driver->name)->toBe('Kojo Antwi')
        ->and($out->deliveries()->where('status', 'delivered')->count())->toBe(1)
        ->and(Delivery::query()->whereDate('delivery_date', '2026-10-24')->where('status', 'pending')->exists())->toBeTrue()
        ->and(Delivery::query()->where('status', 'delivered')->whereNull('delivered_note')->count())->toBeGreaterThan(20);

    // The van keeps moving when driven: two minutes on, it has sent a new position.
    $before = LocationPing::query()->count();
    $this->travel(2)->minutes();
    expect((new DemoSeeder)->tick(CarbonImmutable::now()))->toBeGreaterThan(0)
        ->and(LocationPing::query()->count())->toBeGreaterThan($before);
});
