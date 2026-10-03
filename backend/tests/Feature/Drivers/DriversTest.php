<?php

use Illuminate\Support\Carbon;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Drivers\Actions\ApproveDriver;
use Modules\Drivers\Actions\ApproveVehicle;
use Modules\Drivers\Actions\MarkStatementPaid;
use Modules\Drivers\Actions\OverrideVettingCheck;
use Modules\Drivers\Actions\RecordAssessment;
use Modules\Drivers\Actions\RecordVettingCheck;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Drivers\Enums\AssessmentOutcome;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\VettingResult;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Drivers\Models\VettingItem;
use Modules\Messaging\Models\EventLog;

beforeEach(fn () => syncAccess());

function newDriver(): Driver
{
    return app(RegisterDriver::class)->handle(['name' => 'Kwame Mensah', 'phone' => '0241234567', 'monthly_commission' => 30000]);
}

it('gives a new driver and his mate mobile accounts with the right roles', function () {
    $driver = newDriver();
    $mate = app(RegisterMate::class)->handle($driver, 'Kofi', '0201112222');

    expect($driver->status)->toBe(DriverStatus::Pending)
        ->and($driver->phone)->toBe('+233241234567')
        ->and($driver->account->hasRole('driver'))->toBeTrue()
        ->and($mate->account->hasRole('mate'))->toBeTrue()
        ->and($driver->organisation->slug)->toBe('trustride');
});

it('keeps the rider role when a rider becomes a mate', function () {
    $account = Account::create(['phone' => '+233201112222'])->assignRole('rider');
    app(RegisterMate::class)->handle(newDriver(), 'Kofi', '0201112222');

    expect($account->fresh()->roles->pluck('name')->sort()->values()->all())->toBe(['mate', 'rider']);
});

it('refuses a second driver with the same phone number', function () {
    newDriver();

    expect(fn () => newDriver())->toThrow(DomainException::class);
});

it('will not approve a driver until every required check is passed or overridden', function () {
    $driver = newDriver();
    $officer = staff(Staff::SUPER_ADMIN);
    $items = VettingItem::for($driver)->get();

    expect(fn () => app(ApproveDriver::class)->handle($driver, $officer))->toThrow(DomainException::class, 'Vetting is not finished');

    foreach ($items as $item) {
        app(RecordVettingCheck::class)->handle($driver, $item, VettingResult::Passed, $officer);
    }

    $failed = app(RecordVettingCheck::class)->handle($driver, $items->first(), VettingResult::Failed, $officer, 'Licence expired last month');

    expect(fn () => app(ApproveDriver::class)->handle($driver, $officer))->toThrow(DomainException::class, $items->first()->label);

    // An override must say why, and it records who.
    expect(fn () => app(OverrideVettingCheck::class)->handle($failed, $officer, ' '))->toThrow(DomainException::class);
    app(OverrideVettingCheck::class)->handle($failed, $officer, 'Renewal receipt seen, new licence due Friday');

    app(ApproveDriver::class)->handle($driver, $officer);

    expect($driver->fresh()->status)->toBe(DriverStatus::Approved)
        ->and($driver->fresh()->approved_by)->toBe($officer->id)
        ->and($failed->fresh()->overridden_by)->toBe($officer->id)
        ->and($failed->fresh()->override_reason)->toContain('Renewal receipt')
        ->and(EventLog::where('name', 'driver.approved')->count())->toBe(1);
});

it('only overrides failed checks, and a new result clears the override', function () {
    $driver = newDriver();
    $officer = staff(Staff::SUPER_ADMIN);
    $item = VettingItem::for($driver)->first();

    $passed = app(RecordVettingCheck::class)->handle($driver, $item, VettingResult::Passed, $officer);
    expect(fn () => app(OverrideVettingCheck::class)->handle($passed, $officer, 'why not'))->toThrow(DomainException::class);

    $failed = app(RecordVettingCheck::class)->handle($driver, $item, VettingResult::Failed, $officer);
    app(OverrideVettingCheck::class)->handle($failed, $officer, 'Seen in person');
    $again = app(RecordVettingCheck::class)->handle($driver, $item, VettingResult::Failed, $officer);

    expect($again->id)->toBe($failed->id)->and($again->isOverridden())->toBeFalse();
});

it('keeps driver checks and vehicle checks apart', function () {
    $driver = newDriver();
    $officer = staff(Staff::SUPER_ADMIN);
    $vehicle = Vehicle::create(['driver_id' => $driver->id, 'registration_number' => 'GT 1234-22', 'colour' => 'White', 'seats_total' => 15]);
    $vehicleItem = VettingItem::for($vehicle)->first();

    expect(fn () => app(RecordVettingCheck::class)->handle($driver, $vehicleItem, VettingResult::Passed, $officer))->toThrow(DomainException::class)
        ->and(fn () => app(ApproveVehicle::class)->handle($vehicle))->toThrow(DomainException::class);

    VettingItem::for($vehicle)->get()->each(fn ($item) => app(RecordVettingCheck::class)->handle($vehicle, $item, VettingResult::Passed, $officer));

    expect(app(ApproveVehicle::class)->handle($vehicle)->isApproved())->toBeTrue();
});

it('runs a driver\'s month: assessment, then payment, then locked', function () {
    $driver = newDriver();
    $operator = staff(Staff::SUPER_ADMIN);
    $september = Carbon::parse('2026-09-17');

    $statement = app(RecordAssessment::class)->handle($driver, $september, AssessmentOutcome::Met, $operator, 'Held every seat', bonus: 5000);

    expect($statement->period->toDateString())->toBe('2026-09-01')
        ->and($statement->commission_amount)->toBe(30000)
        ->and($statement->total())->toBe(35000);

    // Changing his commission later does not rewrite a month already opened.
    $driver->update(['monthly_commission' => 40000]);
    $statement = app(RecordAssessment::class)->handle($driver, $september, AssessmentOutcome::Met, $operator, 'Held every seat', bonus: 5000);
    expect($statement->commission_amount)->toBe(30000);

    expect(fn () => app(MarkStatementPaid::class)->handle($statement, $operator, ''))->toThrow(DomainException::class);

    app(MarkStatementPaid::class)->handle($statement, $operator, 'MoMo 0045821');

    expect($statement->fresh()->isPaid())->toBeTrue()
        ->and(EventLog::where('name', 'driver.statement_paid')->first()->payload['amount'])->toBe('350.00')
        ->and(fn () => app(MarkStatementPaid::class)->handle($statement->fresh(), $operator, 'again'))->toThrow(DomainException::class)
        ->and(fn () => app(RecordAssessment::class)->handle($driver, $september, AssessmentOutcome::NotMet, $operator))->toThrow(DomainException::class);
});

it('will not pay a month that has no assessment', function () {
    $driver = newDriver();
    $statement = $driver->statements()->create(['period' => '2026-09-01', 'commission_amount' => 30000]);

    expect(fn () => app(MarkStatementPaid::class)->handle($statement, staff(Staff::SUPER_ADMIN), 'ref'))->toThrow(DomainException::class, 'assessment');
});
