<?php

namespace App\Api\Support;

use Modules\Access\Models\Account;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Mate;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Models\Seat;

/**
 * What we know about the person writing in: her name, how she uses the app, and what she rides.
 * Support itself knows none of this. The side gathers it and hands it over, so an operator
 * opening the case doesn't have to look her up first.
 */
class Reporter
{
    /** @return array{name: ?string, role: ?string, context: ?string} */
    public function describe(Account $account): array
    {
        if ($rider = Rider::query()->where('account_id', $account->id)->first()) {
            $seats = Seat::query()
                ->where('rider_id', $rider->id)
                ->whereIn('status', [SeatStatus::Reserved, SeatStatus::Active])
                ->with(['run.route.stops', 'run.driver', 'run.vehicle'])
                ->get()
                ->map(fn (Seat $seat) => "{$seat->run->route->name}, from {$seat->run->route->stops->firstWhere('id', $seat->boardsAt())->name} at {$seat->pickupTime()} to {$seat->run->route->stops->firstWhere('id', $seat->getsOffAt())->name}, car {$seat->run->vehicle->registration_number}, driver {$seat->run->driver->name}")
                ->implode('. ');

            return ['name' => $rider->name, 'role' => 'rider', 'context' => $seats ?: 'No seat yet.'];
        }

        if ($driver = Driver::query()->where('account_id', $account->id)->first()) {
            return ['name' => $driver->name, 'role' => 'driver', 'context' => null];
        }

        if ($mate = Mate::query()->where('account_id', $account->id)->with('driver')->first()) {
            return ['name' => $mate->name, 'role' => 'mate', 'context' => $mate->driver ? "Mate for {$mate->driver->name}." : null];
        }

        return ['name' => $account->name, 'role' => null, 'context' => null];
    }
}
