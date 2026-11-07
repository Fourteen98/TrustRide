<?php

use App\Admin\Pages\PilotReport;
use App\Admin\Reports\PilotNumbers;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Access\Filament\Resources\AccountResource;
use Modules\Access\Filament\Resources\AccountResource\Pages\ManageAccounts;
use Modules\Access\Filament\Resources\RoleResource;
use Modules\Access\Filament\Resources\RoleResource\Pages\ManageRoles;
use Modules\Access\Filament\Resources\StaffResource;
use Modules\Access\Models\Account;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;
use Modules\Access\Models\Staff;
use Modules\Payments\Actions\ApplyOutcome;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\StartPlanPurchase;
use Modules\Subscriptions\Models\Plan;
use Modules\Trips\Actions\MarkPickup;
use Modules\Trips\Actions\StartTrip;
use Modules\Trips\Enums\PickupStatus;

beforeEach(function () {
    syncAccess();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('sends a guest to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('shows a super admin every access screen', function () {
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');

    $this->get('/admin')->assertOk();
    $this->get('/admin/roles')->assertOk()->assertSee('Operator');
    $this->get('/admin/staff')->assertOk();
    $this->get('/admin/accounts')->assertOk();
});

it('builds the menu from permissions', function () {
    $this->actingAs(staff('operator'), 'platform');

    expect(AccountResource::canViewAny())->toBeTrue()
        ->and(RoleResource::canViewAny())->toBeFalse()
        ->and(StaffResource::canViewAny())->toBeFalse();

    $this->get('/admin/accounts')->assertOk();
    $this->get('/admin/roles')->assertForbidden();
    $this->get('/admin/staff')->assertForbidden();
});

it('creates a role with groups from the console', function () {
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');
    $group = PermissionGroup::where('key', 'drivers.vetting')->first();

    Livewire::test(ManageRoles::class)
        ->callAction('create', [
            'label' => 'Vetting officer',
            'name' => 'vetting-officer',
            'guard_name' => 'platform',
            'group_ids' => [$group->id],
        ])
        ->assertHasNoActionErrors();

    expect(Role::findByName('vetting-officer', 'platform')->hasPermissionTo('drivers.vet'))->toBeTrue();
});

it('lets an operator add a mate who is also a rider', function () {
    $this->actingAs(staff('operator'), 'platform');
    $roles = Role::whereIn('name', ['mate', 'rider'])->pluck('id')->all();

    Livewire::test(ManageAccounts::class)
        ->callAction('create', ['phone' => '0241234567', 'name' => 'Kofi', 'role_ids' => $roles, 'is_active' => true])
        ->assertHasNoActionErrors();

    $account = Account::where('phone', '+233241234567')->first();

    expect($account->roles->pluck('name')->sort()->values()->all())->toBe(['mate', 'rider']);
});

it('adds up the pilot report from the records, and keeps it from staff who may not see the money', function () {
    syncAccess();
    Carbon::setTestNow('2026-10-07 09:00:00'); // a Wednesday
    config(['payments.driver' => 'fake', 'payments.fake.settle_after_seconds' => -1]);
    $network = network();
    $run = run($network);
    $operator = staff('operator');

    // Two riders. Ama paid through the app this week. Esi's two-week plan ended last week and she renewed.
    $ama = application($network, 'Ama Owusu');
    $esi = application($network, 'Esi Boateng');
    foreach ([$ama, $esi] as $application) {
        app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], $operator);
    }
    $month = Plan::query()->where('duration_days', 30)->first();
    app(StartPlanPurchase::class)->handle($ama->rider, $month, '+233241234567', 'mtn');
    app(ApplyOutcome::class)->handle(Payment::query()->sole(), true, 'X', 'test');

    Carbon::setTestNow('2026-09-18 09:00:00');
    app(ActivateSubscription::class)->handle($esi->rider, Plan::query()->where('duration_days', 14)->first(), $operator);
    Carbon::setTestNow('2026-10-07 09:00:00');
    app(ActivateSubscription::class)->handle($esi->rider, $month, $operator);

    // Today's trip left 20 minutes late. One rider was picked up, one was not there.
    Carbon::setTestNow('2026-10-07 06:50:00');
    $trip = app(StartTrip::class)->handle($run, null, $operator);
    app(MarkPickup::class)->handle($trip, $ama->rider_id, PickupStatus::PickedUp);
    app(MarkPickup::class)->handle($trip, $esi->rider_id, PickupStatus::Missed);
    Carbon::setTestNow('2026-10-07 09:00:00');

    $numbers = app(PilotNumbers::class);
    [$thisWeek, $lastWeek] = [$numbers->weeks()[0], $numbers->weeks()[1]];

    expect($numbers->now())->toMatchArray(['riders_riding' => 2, 'seats_taken' => 2, 'seats_held' => 12, 'applications_waiting' => 0])
        ->and($thisWeek)->toMatchArray([
            'label' => '5 Oct to 11 Oct', 'riders' => 2, 'applications' => 2, 'money_in' => 6000,
            'trips_due' => 1, 'trips_run' => 1, 'trips_on_time' => 0, 'picked_up' => 1, 'missed' => 1,
        ])
        ->and($lastWeek)->toMatchArray(['label' => '28 Sep to 4 Oct', 'riders' => 1, 'ended' => 1, 'renewed' => 1, 'money_in' => 0])
        ->and($thisWeek['sms'])->toBeGreaterThan(0)
        ->and(PilotReport::share(1, 4))->toBe('1 of 4 (25%)')
        ->and(PilotReport::share(0, 0))->toBe('–');

    $this->actingAs($operator, 'platform');
    $this->get('/admin/pilot-report')->assertForbidden();

    $this->flushSession(); // or the first person's session logs the second one out
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');
    $this->get('/admin/pilot-report')->assertOk()->assertSee('GHS 60')->assertSee('2 of 12 (17%)')->assertSee('0 of 1 (0%)')->assertSee('1 of 2 (50%)')->assertSee('1 of 1 (100%)');
});
