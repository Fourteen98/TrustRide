<?php

namespace Modules\Subscriptions\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Riders\Actions\ApproveApplication;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Models\Run;

/**
 * Approves an application by naming the run or runs that will carry her. Each run reserves a seat.
 * If a run is full, nothing is saved and the application stays pending.
 */
class ApproveApplicationWithSeats
{
    public function __construct(private ReserveSeat $reserveSeat, private ApproveApplication $approve) {}

    /** @param  list<int>  $runIds  One run, or two in opposite directions */
    public function handle(RiderApplication $application, array $runIds, Staff $by): RiderApplication
    {
        $runs = Run::query()->findMany(array_unique($runIds));

        if ($runs->isEmpty()) {
            throw new DomainException('Choose the run that will carry her.');
        }

        if ($runs->count() > 2 || $runs->pluck('direction')->unique()->count() !== $runs->count()) {
            throw new DomainException('Choose one run, or one in each direction.');
        }

        if ($runs->contains(fn (Run $run) => $run->route_id !== $application->route_id)) {
            throw new DomainException('That run is on a different route from the one she asked for.');
        }

        return DB::transaction(function () use ($application, $runs, $by) {
            foreach ($runs as $run) {
                $this->reserveSeat->handle($application->rider, $run, $application->stop_id, $application);
            }

            return $this->approve->handle($application, $by);
        });
    }
}
