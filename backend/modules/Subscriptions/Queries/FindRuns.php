<?php

namespace Modules\Subscriptions\Queries;

use App\Support\Maps\Distance;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Network\Models\Stop;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Models\Run;

/**
 * "Where to?" Runs that take her to a place she types: a stop's name, its landmark, or a route's destination.
 *
 * For each run that reaches a matching stop, the best place to get on before it: the stop nearest to her
 * when the phone gives her position, otherwise the run's first stop. Nearest first, then the next to leave.
 * Every run of the network is looked at, which is fine for tens of runs. Past a few hundred, index the stop names.
 */
class FindRuns
{
    /**
     * @return Collection<int, array{run_id: int, route_id: int, route: string, direction: string, departs_at: string, days: list<int>, seats_free: int, apply_stop_id: int, board: array{stop_id: int, name: string, landmark: ?string, time: ?string, metres_away: ?int}, alight: array{stop_id: int, name: string, landmark: ?string, time: ?string}}>
     */
    public function handle(string $place, ?float $latitude = null, ?float $longitude = null, int $limit = 20): Collection
    {
        $needle = Str::lower(trim($place));

        if (Str::length($needle) < 2) {
            return collect();
        }

        return Run::query()
            ->where('is_active', true)
            ->whereHas('route', fn ($route) => $route->where('is_active', true))
            ->with(['route.stops', 'driver'])
            ->withSum('takenSeats as taken_seats_count', 'party')
            ->get()
            ->map(fn (Run $run) => $this->match($run, $needle, $latitude, $longitude))
            ->filter()
            ->sortBy([
                fn (array $a, array $b) => ($a['board']['metres_away'] ?? PHP_INT_MAX) <=> ($b['board']['metres_away'] ?? PHP_INT_MAX),
                // Vans run all day: the next to reach her stop first, the ones already gone today last.
                fn (array $a, array $b) => $this->untilNext($a['board']['time']) <=> $this->untilNext($b['board']['time']),
            ])
            ->take($limit)
            ->values();
    }

    /** @return array<string, mixed>|null */
    private function match(Run $run, string $needle, ?float $latitude, ?float $longitude): ?array
    {
        $stops = $run->route->stops->where('is_active', true)->values();
        $stops = $run->direction === Direction::Outbound ? $stops : $stops->reverse()->values();
        $routeMatches = Str::contains(Str::lower($run->route->destination ?? ''), $needle) && $run->direction === Direction::Outbound;

        // Where she wants to get down: the first stop on the way that matches, or the end of a route whose destination matches.
        $to = $stops->search(fn (Stop $stop, int $index) => $index > 0 && $this->matches($stop, $needle));

        if ($to === false && $routeMatches) {
            $to = $stops->count() - 1;
        }

        if ($to === false || $to < 1) {
            return null;
        }

        $before = $stops->slice(0, $to);
        $near = $latitude !== null && $longitude !== null;
        $board = $near
            ? $before->filter(fn (Stop $stop) => $stop->latitude !== null)->sortBy(fn (Stop $stop) => Distance::metres($latitude, $longitude, $stop->latitude, $stop->longitude))->first() ?? $before->first()
            : $before->first();
        $alight = $stops[$to];

        return [
            'run_id' => $run->id,
            'route_id' => $run->route_id,
            'route' => $run->route->name,
            'direction' => $run->direction->value,
            'departs_at' => $run->departureTime(),
            'days' => array_map('intval', $run->days_of_week ?? []),
            'seats_free' => max(0, $run->seats_held - (int) $run->taken_seats_count),
            // An application names one stop, her own: where she gets on in the morning and gets down in the evening.
            'apply_stop_id' => $run->direction === Direction::Outbound ? $board->id : $alight->id,
            'board' => [
                'stop_id' => $board->id,
                'name' => $board->name,
                'landmark' => $board->landmark,
                'time' => $run->timeAtStop($board->id),
                'metres_away' => $near && $board->latitude !== null ? (int) round(Distance::metres($latitude, $longitude, $board->latitude, $board->longitude)) : null,
            ],
            'alight' => [
                'stop_id' => $alight->id,
                'name' => $alight->name,
                'landmark' => $alight->landmark,
                'time' => $run->timeAtStop($alight->id),
            ],
        ];
    }

    /** Minutes from now until a time today, counting round to tomorrow once it has passed. */
    private function untilNext(?string $time): int
    {
        if ($time === null) {
            return PHP_INT_MAX;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60 + $minutes - (now()->hour * 60 + now()->minute) + 1440) % 1440;
    }

    private function matches(Stop $stop, string $needle): bool
    {
        return collect([$stop->name, $stop->landmark])
            ->filter()
            ->contains(fn (string $text) => Str::contains(Str::lower($text), $needle));
    }
}
