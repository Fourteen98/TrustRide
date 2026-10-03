<?php

use App\Support\Scope;
use Modules\Access\Actions\SetGroupPermissions;
use Modules\Access\Actions\SetRoleAccess;
use Modules\Access\Actions\SyncDeclaredAccess;
use Modules\Access\Exceptions\ScopeMismatch;
use Modules\Access\Models\Account;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;

beforeEach(fn () => syncAccess());

it('stores what the modules declare, in the right scope', function () {
    expect(Permission::where('name', 'drivers.vet')->value('guard_name'))->toBe('platform')
        ->and(Permission::where('name', 'trips.pickups.mark')->value('guard_name'))->toBe('app')
        ->and(Permission::where('name', 'drivers.vet')->value('module'))->toBe('Drivers')
        ->and(Role::where('name', 'mate')->value('screens'))->toBe('mate');
});

it('gives the system roles their default groups', function () {
    $mate = Role::findByName('mate', 'app');
    $driver = Role::findByName('driver', 'app');

    expect($mate->hasPermissionTo('trips.pickups.mark'))->toBeTrue()
        ->and($driver->hasPermissionTo('trips.location.send'))->toBeTrue()
        ->and($driver->hasPermissionTo('trips.pickups.mark'))->toBeFalse();
});

it('is safe to run twice and keeps an admin\'s changes', function () {
    $operator = Role::findByName('operator', 'platform');
    app(SetRoleAccess::class)->handle($operator, []);

    $counts = [Permission::count(), PermissionGroup::count(), Role::count()];
    app(SyncDeclaredAccess::class)->handle();

    expect([Permission::count(), PermissionGroup::count(), Role::count()])->toBe($counts)
        ->and($operator->fresh()->permissions)->toBeEmpty();
});

it('gives a role its groups plus its single ticks', function () {
    $role = Role::create(['name' => 'vetting-officer', 'guard_name' => 'platform', 'label' => 'Vetting officer']);
    $group = PermissionGroup::where('key', 'drivers.vetting')->first();
    $extra = Permission::findByName('riders.view', 'platform');

    app(SetRoleAccess::class)->handle($role, [$group->id], [$extra->id]);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['drivers.assess', 'drivers.vet', 'drivers.vetting.override', 'riders.view']);
});

it('passes a permission added to a group on to every role that has the group', function () {
    $group = PermissionGroup::where('key', 'trips.pickups')->first();
    $share = Permission::findByName('trips.share', 'app');
    $account = Account::create(['phone' => '+233241234567'])->assignRole('mate');

    expect($account->can('trips.share'))->toBeFalse();

    app(SetGroupPermissions::class)->handle($group, [...$group->permissions->pluck('id'), $share->id]);

    expect($account->fresh()->can('trips.share'))->toBeTrue();
});

it('refuses to mix scopes', function () {
    $platformRole = Role::findByName('operator', 'platform');
    $appGroup = PermissionGroup::where('guard_name', 'app')->first();
    $appPermission = Permission::where('guard_name', 'app')->first();
    $platformGroup = PermissionGroup::where('guard_name', 'platform')->first();

    expect(fn () => app(SetRoleAccess::class)->handle($platformRole, [$appGroup->id]))->toThrow(ScopeMismatch::class)
        ->and(fn () => app(SetRoleAccess::class)->handle($platformRole, [], [$appPermission->id]))->toThrow(ScopeMismatch::class)
        ->and(fn () => app(SetGroupPermissions::class)->handle($platformGroup, [$appPermission->id]))->toThrow(ScopeMismatch::class);
});

it('never lets a mobile account hold a platform role', function () {
    $account = Account::create(['phone' => '+233241234567']);

    expect(fn () => $account->assignRole(Role::findByName('operator', 'platform')))->toThrow(Exception::class)
        ->and(Scope::App->value)->toBe('app');
});
