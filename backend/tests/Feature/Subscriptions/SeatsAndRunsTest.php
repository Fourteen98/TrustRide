<?php

use Modules\Access\Models\Staff;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\VehicleStatus;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RiderStatus;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;

beforeEach(fn () => syncAccess());

it('only puts a run on an approved driver and his own approved vehicle', function () {
    $network = network(seats: 15);

    expect(fn () => run($network, ['seats_held' => 16]))->toThrow(DomainException::class, 'between 1 and 15')
        ->and(fn () => run($network, ['days_of_week' => []]))->toThrow(DomainException::class, 'day');

    $network['vehicle']->update(['status' => VehicleStatus::Pending]);
    expect(fn () => run($network))->toThrow(DomainException::class, 'not approved');
    $network['vehicle']->update(['status' => VehicleStatus::Approved]);

    $network['driver']->update(['status' => DriverStatus::Pending]);
    expect(fn () => run($network))->toThrow(DomainException::class, 'not approved');
    $network['driver']->update(['status' => DriverStatus::Approved]);

    $other = network();
    expect(fn () => run($network, ['vehicle_id' => $other['vehicle']->id]))->toThrow(DomainException::class, 'different driver');

    $run = run($network);
    expect($run->seatsFree())->toBe(12)->and($run->timeAtStop($network['stops'][2]->id))->toBe('06:50');
});

it('works out stop times on the way back by travelling the route in reverse', function () {
    $network = network();
    $return = run($network, ['direction' => 'return', 'departs_at' => '17:30']);

    // Stops are 0, 10, 20 and 30 minutes from the start. Going back, the first stop is reached last.
    expect($return->timeAtStop($network['stops'][3]->id))->toBe('17:30')
        ->and($return->timeAtStop($network['stops'][0]->id))->toBe('18:00');
});

it('approves an application by reserving a seat on a run', function () {
    $network = network();
    $run = run($network);
    $application = application($network);

    app(ApproveApplicationWithSeats::class)->handle($application, [$run->id], staff(Staff::SUPER_ADMIN));

    $seat = Seat::first();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Approved)
        ->and($application->rider->fresh()->status)->toBe(RiderStatus::Approved)
        ->and($seat->status)->toBe(SeatStatus::Reserved)
        ->and($seat->pickupTime())->toBe('06:30')
        ->and($run->seatsFree())->toBe(11);
});

it('refuses when the run is full and leaves the application pending', function () {
    $network = network();
    $run = run($network, ['seats_held' => 1]);
    $operator = staff(Staff::SUPER_ADMIN);

    app(ApproveApplicationWithSeats::class)->handle(application($network, 'Ama'), [$run->id], $operator);
    $second = application($network, 'Esi');

    expect(fn () => app(ApproveApplicationWithSeats::class)->handle($second, [$run->id], $operator))
        ->toThrow(DomainException::class, 'full')
        ->and($second->fresh()->status)->toBe(ApplicationStatus::Pending)
        ->and(Seat::count())->toBe(1);
});

it('can seat her both ways, but saves nothing if the second run is full', function () {
    $network = network();
    $operator = staff(Staff::SUPER_ADMIN);
    $morning = run($network);
    $evening = run($network, ['direction' => 'return', 'departs_at' => '17:30', 'seats_held' => 1]);
    $another = run($network, ['departs_at' => '07:00']);

    $first = application($network, 'Ama');

    expect(fn () => app(ApproveApplicationWithSeats::class)->handle($first, [$morning->id, $another->id], $operator))
        ->toThrow(DomainException::class, 'one in each direction')
        ->and(fn () => app(ApproveApplicationWithSeats::class)->handle($first, [], $operator))->toThrow(DomainException::class);

    app(ApproveApplicationWithSeats::class)->handle($first, [$morning->id, $evening->id], $operator);
    expect(Seat::count())->toBe(2);

    $second = application($network, 'Esi');

    expect(fn () => app(ApproveApplicationWithSeats::class)->handle($second, [$morning->id, $evening->id], $operator))
        ->toThrow(DomainException::class, 'full')
        ->and(Seat::count())->toBe(2)
        ->and($second->fresh()->isPending())->toBeTrue();
});

it('refuses a run on a different route and the destination as a pickup stop', function () {
    $network = network();
    $elsewhere = network();
    $operator = staff(Staff::SUPER_ADMIN);

    expect(fn () => app(ApproveApplicationWithSeats::class)->handle(application($network), [run($elsewhere)->id], $operator))
        ->toThrow(DomainException::class, 'different route');

    $atDestination = application($network, 'Esi', ['stop_id' => $network['stops'][3]->id]);

    expect(fn () => app(ApproveApplicationWithSeats::class)->handle($atDestination, [run($network)->id], $operator))
        ->toThrow(DomainException::class, 'destination');
});

it('will not shrink a run below the riders already on it', function () {
    $network = network();
    $run = run($network, ['seats_held' => 2]);
    $operator = staff(Staff::SUPER_ADMIN);

    app(ApproveApplicationWithSeats::class)->handle(application($network, 'Ama'), [$run->id], $operator);
    app(ApproveApplicationWithSeats::class)->handle(application($network, 'Esi'), [$run->id], $operator);

    expect(fn () => app(SaveRun::class)->handle(['seats_held' => 1], $run))->toThrow(DomainException::class, 'more riders');
});
