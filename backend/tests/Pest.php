<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Modules\Access\Actions\SyncDeclaredAccess;
use Modules\Access\Models\Account;
use Modules\Access\Models\Organisation;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\VehicleStatus;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Network\Actions\SetRouteStops;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Models\Run;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/** Store the declared permissions, groups and system roles. */
function syncAccess(): void
{
    app(SyncDeclaredAccess::class)->handle();
}

function staff(string ...$roles): Staff
{
    $staff = Staff::query()->create([
        'organisation_id' => Organisation::platformOwner()->id,
        'name' => 'Test Staff',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password-123',
    ]);

    return $staff->assignRole($roles);
}

/**
 * A route with stops, an approved driver with a vehicle. Enough to hang a run on.
 *
 * @return array{route: Route, stops: Collection, driver: Driver, vehicle: Vehicle}
 */
function network(int $seats = 15): array
{
    $stops = collect(['Barrier', 'Old Barrier', 'Mallam', 'Circle'])
        ->map(fn (string $name) => Stop::query()->create(['name' => $name]));

    $route = Route::query()->create(['name' => 'Bortianor to Circle', 'destination' => 'Circle']);

    app(SetRouteStops::class)->handle(
        $route,
        $stops->values()->map(fn ($stop, $i) => ['stop_id' => $stop->id, 'minutes_from_start' => $i * 10])->all(),
    );

    $driver = app(RegisterDriver::class)->handle([
        'name' => 'Kwame Mensah', 'phone' => '024'.fake()->unique()->numerify('#######'), 'monthly_commission' => 30000,
    ]);
    $driver->update(['status' => DriverStatus::Approved]);

    $vehicle = Vehicle::query()->create([
        'driver_id' => $driver->id, 'registration_number' => 'GT '.fake()->unique()->numerify('####').'-22',
        'colour' => 'White', 'seats_total' => $seats, 'status' => VehicleStatus::Approved,
    ]);

    return compact('route', 'stops', 'driver', 'vehicle');
}

/** A weekday-morning run on the network() fixture. */
function run(array $network, array $overrides = []): Run
{
    return app(SaveRun::class)->handle([
        'route_id' => $network['route']->id,
        'driver_id' => $network['driver']->id,
        'vehicle_id' => $network['vehicle']->id,
        'direction' => 'outbound',
        'departs_at' => '06:30',
        'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
        'seats_held' => 12,
        ...$overrides,
    ]);
}

/** A rider with a pending application for the first stop on the network() fixture. */
function application(array $network, string $name = 'Ama Owusu', array $overrides = []): RiderApplication
{
    $account = Account::query()->create(['phone' => '+23324'.fake()->unique()->numerify('#######')]);
    $account->assignRole('rider');

    return app(SubmitApplication::class)->handle($account, [
        'name' => $name,
        'route_id' => $network['route']->id,
        'stop_id' => $network['stops'][0]->id,
        'morning_time' => '06:30',
        ...$overrides,
    ]);
}
