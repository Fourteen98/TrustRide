<?php

namespace Modules\Trips\Queries;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Modules\Access\Models\Account;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Mate;
use Modules\Subscriptions\Models\Run;

/**
 * The runs an account works on: the driver's own runs, or the runs of the driver a mate rides with.
 * Every crew endpoint goes through here, so a phone can only touch its own van's runs.
 */
class CrewRuns
{
    /** @return Collection<int, Run> today's runs, earliest first */
    public function today(Account $account): Collection
    {
        $today = CarbonImmutable::today();

        return Run::query()
            ->whereIn('driver_id', $this->driverIds($account))
            ->where('is_active', true)
            ->with(['route.stops', 'driver', 'vehicle'])
            ->orderBy('departs_at')
            ->get()
            ->filter(fn (Run $run) => $run->runsOn($today))
            ->values();
    }

    public function find(Account $account, int $runId): Run
    {
        return Run::query()
            ->whereIn('driver_id', $this->driverIds($account))
            ->with(['route.stops', 'driver', 'vehicle'])
            ->find($runId) ?? throw new DomainException('That run is not yours.');
    }

    /** @return list<int> */
    private function driverIds(Account $account): array
    {
        return [
            ...Driver::query()->where('account_id', $account->id)->pluck('id')->all(),
            ...Mate::query()->where('account_id', $account->id)->where('is_active', true)->pluck('driver_id')->all(),
        ];
    }
}
