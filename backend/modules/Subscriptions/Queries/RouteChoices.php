<?php

namespace Modules\Subscriptions\Queries;

use Illuminate\Support\Collection;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Models\Run;

/**
 * What a rider can choose from when she applies: each route we serve, the stops she can use,
 * and the times a van actually runs. Routes with no run yet are left out.
 */
class RouteChoices
{
    /**
     * @return Collection<int, array{id: int, name: string, destination: string, stops: list<array{id: int, name: string, landmark: ?string}>, morning_times: list<array{time: string, seats_free: bool}>, evening_times: list<array{time: string, seats_free: bool}>}>
     */
    public function handle(): Collection
    {
        $runs = Run::query()->where('is_active', true)->withCount('takenSeats')->orderBy('departs_at')->get()->groupBy('route_id');

        return Route::query()
            ->where('is_active', true)
            ->whereIn('id', $runs->keys())
            ->with('stops')
            ->orderBy('name')
            ->get()
            ->map(fn (Route $route) => [
                'id' => $route->id,
                'name' => $route->name,
                'destination' => $route->destination,
                // The last stop is the destination, so it is not somewhere she is picked up.
                'stops' => $route->stops->slice(0, -1)->where('is_active', true)->map(fn (Stop $stop) => [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'landmark' => $stop->landmark,
                ])->values()->all(),
                'morning_times' => $this->times($runs[$route->id], Direction::Outbound),
                'evening_times' => $this->times($runs[$route->id], Direction::Return),
            ])
            ->values();
    }

    /** @return list<array{time: string, seats_free: bool}> */
    private function times(Collection $runs, Direction $direction): array
    {
        return $runs->where('direction', $direction)
            ->groupBy(fn (Run $run) => $run->departureTime())
            ->map(fn (Collection $sameTime, string $time) => [
                'time' => $time,
                'seats_free' => $sameTime->contains(fn (Run $run) => $run->seats_held > $run->taken_seats_count),
            ])
            ->values()
            ->all();
    }
}
