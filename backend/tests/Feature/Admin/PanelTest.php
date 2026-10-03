<?php

use Filament\Facades\Filament;
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
