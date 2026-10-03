<?php

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Filament\Resources\DriverResource\Pages\ManageDrivers;
use Modules\Drivers\Filament\Resources\DriverStatementResource\Pages\ManageDriverStatements;
use Modules\Drivers\Filament\Resources\VehicleResource\Pages\ManageVehicles;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\DriverStatement;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingItem;
use Modules\Network\Filament\Resources\RouteResource\Pages\ManageRoutes;
use Modules\Network\Filament\Resources\StopResource\Pages\ManageStops;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Filament\Pages\TodaysRiders;
use Modules\Subscriptions\Filament\Resources\ApplicationResource\Pages\ManageApplications;
use Modules\Subscriptions\Filament\Resources\PlanResource\Pages\ManagePlans;
use Modules\Subscriptions\Filament\Resources\RunResource\Pages\ManageRuns;
use Modules\Subscriptions\Filament\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Models\Seat;
use Modules\Subscriptions\Models\Subscription;

beforeEach(function () {
    syncAccess();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Carbon::setTestNow('2026-10-05 09:00:00'); // a Monday
});

it('opens every screen for a super admin', function (string $path) {
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');

    $this->get("/admin/{$path}")->assertOk();
})->with(['stops', 'routes', 'drivers', 'vehicles', 'driver-statements', 'riders', 'applications', 'plans', 'runs', 'subscriptions', 'todays-riders']);

it('shows an operator the day-to-day screens but hides vetting, plan editing and roles', function () {
    $this->actingAs(staff('operator'), 'platform');

    foreach (['stops', 'routes', 'drivers', 'applications', 'runs', 'subscriptions', 'todays-riders'] as $path) {
        $this->get("/admin/{$path}")->assertOk();
    }

    $driver = app(RegisterDriver::class)->handle(['name' => 'Kwame', 'phone' => '0241234567']);

    Livewire::test(ManageDrivers::class)
        ->assertTableActionHidden('vetting', $driver)
        ->assertTableActionHidden('approve', $driver);

    Livewire::test(ManagePlans::class)->assertTableActionHidden('edit', Plan::first());
    $this->get('/admin/roles')->assertForbidden();
});

it('runs the whole path through the console, from an empty system to a paid driver', function () {
    $admin = staff(Staff::SUPER_ADMIN);
    $this->actingAs($admin, 'platform');

    // 1. Stops and a route.
    foreach (['Barrier', 'Mallam', 'Circle'] as $name) {
        Livewire::test(ManageStops::class)->callAction('create', ['name' => $name])->assertHasNoActionErrors();
    }

    [$barrier, $mallam, $circle] = Stop::orderBy('id')->get()->all();

    Livewire::test(ManageRoutes::class)
        ->callAction('create', [
            'name' => 'Bortianor to Circle', 'destination' => 'Circle',
            'stop_rows' => [
                ['stop_id' => $barrier->id, 'minutes_from_start' => 0],
                ['stop_id' => $mallam->id, 'minutes_from_start' => 15],
                ['stop_id' => $circle->id, 'minutes_from_start' => 40],
            ],
        ])
        ->assertHasNoActionErrors();

    $route = Route::first();
    expect($route->stops->pluck('name')->all())->toBe(['Barrier', 'Mallam', 'Circle']);

    // 2. A driver, his vehicle and his mate.
    Livewire::test(ManageDrivers::class)
        ->callAction('create', ['name' => 'Kwame Mensah', 'phone' => '0241234567', 'monthly_commission' => 300])
        ->assertHasNoActionErrors();

    $driver = Driver::first();
    expect($driver->monthly_commission)->toBe(30000)->and($driver->account->hasRole('driver'))->toBeTrue();

    Livewire::test(ManageVehicles::class)
        ->callAction('create', ['driver_id' => $driver->id, 'registration_number' => 'GT 1234-22', 'colour' => 'White', 'seats_total' => 15])
        ->assertHasNoActionErrors();

    $vehicle = Vehicle::first();

    Livewire::test(ManageDrivers::class)
        ->callTableAction('addMate', $driver, ['name' => 'Kofi', 'phone' => '0201112222'])
        ->assertHasNoTableActionErrors();

    expect($driver->mates()->first()->account->hasRole('mate'))->toBeTrue();

    // 3. Vetting. Approval is refused until it is finished. One item fails and is overridden.
    Livewire::test(ManageDrivers::class)->callTableAction('approve', $driver)->assertNotified();
    expect($driver->fresh()->status)->toBe(DriverStatus::Pending);

    $driverItems = VettingItem::for($driver)->get();
    $results = $driverItems->mapWithKeys(fn ($item) => ["result_{$item->id}" => 'passed'])->all();
    $results["result_{$driverItems[0]->id}"] = 'failed';
    $results["notes_{$driverItems[0]->id}"] = 'Licence expired last month';

    Livewire::test(ManageDrivers::class)->callTableAction('vetting', $driver, $results)->assertHasNoTableActionErrors();

    Livewire::test(ManageDrivers::class)->callTableAction('approve', $driver);
    expect($driver->fresh()->status)->toBe(DriverStatus::Pending);

    $failed = $driver->vettingChecks()->where('result', 'failed')->first();

    Livewire::test(ManageDrivers::class)
        ->callTableAction('override', $driver, ['check_id' => $failed->id, 'reason' => 'Renewal receipt seen'])
        ->assertHasNoTableActionErrors();

    expect($failed->fresh()->overridden_by)->toBe($admin->id);

    Livewire::test(ManageDrivers::class)->callTableAction('approve', $driver);
    expect($driver->fresh()->status)->toBe(DriverStatus::Approved);

    Livewire::test(ManageVehicles::class)
        ->callTableAction('vetting', $vehicle, VettingItem::for($vehicle)->get()->mapWithKeys(fn ($item) => ["result_{$item->id}" => 'passed'])->all())
        ->callTableAction('approve', $vehicle);

    expect($vehicle->fresh()->isApproved())->toBeTrue();

    // 4. His run.
    Livewire::test(ManageRuns::class)
        ->callAction('create', [
            'route_id' => $route->id, 'direction' => 'outbound', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            'departs_at' => '06:30', 'seats_held' => 1, 'days_of_week' => [1, 2, 3, 4, 5],
        ])
        ->assertHasNoActionErrors();

    $run = Run::first();
    expect($run->seats_held)->toBe(1);

    // 5. Two riders apply for one seat. The first is approved, the second is refused and then rejected.
    $network = ['route' => $route, 'stops' => collect([$barrier, $mallam, $circle])];
    $ama = application($network, 'Ama Owusu', ['stop_id' => $mallam->id]);
    $esi = application($network, 'Esi Boateng');

    Livewire::test(ManageApplications::class)
        ->callTableAction('approve', $ama, ['run_ids' => [$run->id]])
        ->assertHasNoTableActionErrors();

    expect($ama->fresh()->status)->toBe(ApplicationStatus::Approved)->and(Seat::first()->status)->toBe(SeatStatus::Reserved);

    Livewire::test(ManageApplications::class)->callTableAction('approve', $esi, ['run_ids' => [$run->id]])->assertNotified();
    expect($esi->fresh()->status)->toBe(ApplicationStatus::Pending);

    Livewire::test(ManageApplications::class)
        ->callTableAction('reject', $esi, ['reason' => 'no_capacity'])
        ->assertHasNoTableActionErrors();

    expect($esi->fresh()->status)->toBe(ApplicationStatus::Rejected);

    // 6. Ama pays. An operator activates her subscription.
    Livewire::test(ManageSubscriptions::class)
        ->callAction('activate', ['rider_id' => $ama->rider_id, 'plan_id' => Plan::where('name', '1 month')->value('id')])
        ->assertHasNoActionErrors();

    $subscription = Subscription::first();

    expect($subscription->activated_by)->toBe($admin->id)
        ->and($subscription->ends_on->toDateString())->toBe('2026-11-03')
        ->and(Seat::first()->status)->toBe(SeatStatus::Active);

    // 7. Today's riders shows her at her stop, with the time the van gets there.
    Livewire::test(TodaysRiders::class)
        ->assertSee('Ama Owusu')->assertSee('Mallam')->assertSee('06:45')
        ->assertSee('Kwame Mensah')->assertSee('GT 1234-22')->assertSee('Kofi')
        ->assertDontSee('Esi Boateng');

    // 8. She says she won't ride on Thursday. Told on Monday, so the day is credited.
    Livewire::test(ManageSubscriptions::class)
        ->callTableAction('absence', $subscription, ['date' => '2026-10-08'])
        ->assertHasNoTableActionErrors();

    expect($subscription->fresh()->ends_on->toDateString())->toBe('2026-11-04');

    Livewire::test(TodaysRiders::class, ['date' => '2026-10-08'])->assertSee('Not riding');

    // 9. Month end. Assess the driver, then mark him paid.
    Livewire::test(ManageDrivers::class)
        ->callTableAction('assess', $driver, ['month' => '2026-09-01', 'outcome' => 'met', 'notes' => 'Held every seat', 'bonus' => 50])
        ->assertHasNoTableActionErrors();

    $statement = DriverStatement::first();
    expect($statement->total())->toBe(35000);

    Livewire::test(ManageDriverStatements::class)
        ->callTableAction('markPaid', $statement, ['reference' => 'MoMo 0045821'])
        ->assertHasNoTableActionErrors();

    expect($statement->fresh()->isPaid())->toBeTrue()->and($statement->fresh()->paid_by)->toBe($admin->id);
});
