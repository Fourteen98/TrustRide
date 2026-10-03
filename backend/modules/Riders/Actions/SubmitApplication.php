<?php

namespace Modules\Riders\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Network\Models\Route;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Events\ApplicationSubmitted;
use Modules\Riders\Models\Rider;
use Modules\Riders\Models\RiderApplication;

/** A rider asks for a seat: a route, a pickup stop, and the time or times she wants. */
class SubmitApplication
{
    /** @param  array{name: string, route_id: int, stop_id: int, morning_time?: ?string, evening_time?: ?string}  $data */
    public function handle(Account $account, array $data): RiderApplication
    {
        $route = Route::query()->where('is_active', true)->find($data['route_id']);

        if (! $route) {
            throw new DomainException('We don\'t serve that route.');
        }

        if (! $route->hasStop((int) $data['stop_id'])) {
            throw new DomainException('That stop is not on this route.');
        }

        if (empty($data['morning_time']) && empty($data['evening_time'])) {
            throw new DomainException('Choose a morning time, an evening time, or both.');
        }

        return DB::transaction(function () use ($account, $data, $route) {
            $rider = Rider::query()->firstOrCreate(['account_id' => $account->id], ['name' => $data['name']]);
            $rider->update(['name' => $data['name']]);

            if ($account->name === null) {
                $account->update(['name' => $data['name']]);
            }

            if ($rider->applications()->where('status', ApplicationStatus::Pending)->exists()) {
                throw new DomainException('You already have an application waiting for review.');
            }

            $application = $rider->applications()->create([
                'route_id' => $route->id,
                'stop_id' => $data['stop_id'],
                'morning_time' => $data['morning_time'] ?? null,
                'evening_time' => $data['evening_time'] ?? null,
            ]);

            ApplicationSubmitted::dispatch($application);

            return $application;
        });
    }
}
