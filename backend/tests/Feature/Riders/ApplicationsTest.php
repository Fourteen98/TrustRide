<?php

use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Messaging\Models\EventLog;
use Modules\Network\Models\Stop;
use Modules\Riders\Actions\RejectApplication;
use Modules\Riders\Actions\SubmitApplication;
use Modules\Riders\Actions\WithdrawApplication;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RejectionReason;
use Modules\Riders\Enums\RiderStatus;

beforeEach(fn () => syncAccess());

it('takes an application and logs the event with her as the recipient', function () {
    $application = application(network());

    expect($application->status)->toBe(ApplicationStatus::Pending)
        ->and($application->rider->status)->toBe(RiderStatus::Applicant)
        ->and($application->rider->account->name)->toBe('Ama Owusu');

    $event = EventLog::where('name', 'application.submitted')->first();

    expect($event->payload['stop'])->toBe('Barrier')
        ->and($event->recipients[0]['phone'])->toBe($application->rider->account->phone)
        ->and($event->recipients[0]['channel'])->toBe('sms');
});

it('refuses a stop that is not on the route, a missing time, and a second pending application', function () {
    $network = network();
    $account = Account::create(['phone' => '+233241234567']);
    $elsewhere = Stop::create(['name' => 'Somewhere else']);
    $base = ['name' => 'Ama', 'route_id' => $network['route']->id, 'stop_id' => $network['stops'][0]->id];

    expect(fn () => app(SubmitApplication::class)->handle($account, [...$base, 'stop_id' => $elsewhere->id, 'morning_time' => '06:30']))
        ->toThrow(DomainException::class, 'not on this route')
        ->and(fn () => app(SubmitApplication::class)->handle($account, $base))->toThrow(DomainException::class, 'time');

    app(SubmitApplication::class)->handle($account, [...$base, 'morning_time' => '06:30']);

    expect(fn () => app(SubmitApplication::class)->handle($account, [...$base, 'morning_time' => '07:00']))
        ->toThrow(DomainException::class, 'already have an application');
});

it('rejects with a reason about what we can serve', function () {
    $application = application(network());
    $operator = staff(Staff::SUPER_ADMIN);

    expect(fn () => app(RejectApplication::class)->handle($application, $operator, RejectionReason::Other))->toThrow(DomainException::class);

    app(RejectApplication::class)->handle($application, $operator, RejectionReason::TimeNotServed);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->fresh()->reviewed_by)->toBe($operator->id)
        ->and(EventLog::where('name', 'application.rejected')->first()->payload['reason'])->toContain('time')
        ->and(fn () => app(RejectApplication::class)->handle($application->fresh(), $operator, RejectionReason::NoCapacity))->toThrow(DomainException::class);
});

it('lets her withdraw while it is pending, and apply again after', function () {
    $network = network();
    $application = application($network);

    app(WithdrawApplication::class)->handle($application);

    $second = app(SubmitApplication::class)->handle($application->rider->account, [
        'name' => 'Ama Owusu', 'route_id' => $network['route']->id, 'stop_id' => $network['stops'][1]->id, 'evening_time' => '17:30',
    ]);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Withdrawn)->and($second->isPending())->toBeTrue();
});
