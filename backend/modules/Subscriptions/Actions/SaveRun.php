<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Network\Models\Route;
use Modules\Subscriptions\Models\Run;

/** Creates or changes a run. A run needs an approved driver, his approved vehicle, and a route with stops. */
class SaveRun
{
    public function handle(array $data, ?Run $run = null): Run
    {
        $run ??= new Run;
        $run->fill($data);

        $driver = Driver::query()->findOrFail($run->driver_id);
        $vehicle = Vehicle::query()->findOrFail($run->vehicle_id);
        $route = Route::query()->findOrFail($run->route_id);

        if (! $driver->isApproved()) {
            throw new DomainException("{$driver->name} is not approved yet. Finish his vetting first.");
        }

        if ($vehicle->driver_id !== $driver->id) {
            throw new DomainException('That vehicle belongs to a different driver.');
        }

        if (! $vehicle->isApproved()) {
            throw new DomainException("Vehicle {$vehicle->registration_number} is not approved yet.");
        }

        if ($route->stops()->count() < 2) {
            throw new DomainException('Add the route\'s stops before putting a run on it.');
        }

        if ($run->seats_held < 1 || $run->seats_held > $vehicle->seats_total) {
            throw new DomainException("Seats held must be between 1 and {$vehicle->seats_total}, the vehicle's total.");
        }

        if (empty($run->days_of_week)) {
            throw new DomainException('Choose at least one day of the week.');
        }

        if ($run->exists && $run->seats_held < $run->takenSeats()->count()) {
            throw new DomainException('There are more riders on this run than that. Move some first.');
        }

        $run->days_of_week = array_values(array_unique(array_map('intval', $run->days_of_week)));
        $run->save();

        return $run;
    }
}
