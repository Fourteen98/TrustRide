<?php

namespace Modules\Trips\Actions;

use App\Support\Recipient;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Account;
use Modules\Access\Models\Staff;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders;
use Modules\Trips\Events\TripStarted;
use Modules\Trips\Models\Trip;

/**
 * The van sets off. Pressed by the driver or the mate, or by an operator when the app is off.
 * One trip per run per day. Pressing start twice gives back the same trip.
 * Everyone riding today is told.
 */
class StartTrip
{
    public function __construct(private TodaysRiders $riders) {}

    public function handle(Run $run, ?Account $by = null, ?Staff $staff = null): Trip
    {
        $today = CarbonImmutable::today();

        if (! $run->is_active || ! $run->runsOn($today)) {
            throw new DomainException('This run doesn\'t go today.');
        }

        return DB::transaction(function () use ($run, $by, $staff, $today) {
            $trip = Trip::query()->where('run_id', $run->id)->where('date', $today->toDateString())->lockForUpdate()->first();

            if ($trip) {
                return $trip->isRunning() ? $trip : throw new DomainException('Today\'s trip has already ended.');
            }

            $trip = Trip::query()->create([
                'run_id' => $run->id,
                'date' => $today,
                'started_at' => now(),
                'started_by_account' => $by?->id,
                'started_by_staff' => $staff?->id,
            ]);

            $people = $this->riders->handle($run, $today)
                ->reject(fn (array $rider) => $rider['absent'])
                ->map(fn (array $rider) => new Recipient('rider', $rider['phone'], $rider['channel'], $rider['name']))
                ->values()->all();

            TripStarted::dispatch($trip, $people);

            return $trip;
        });
    }
}
