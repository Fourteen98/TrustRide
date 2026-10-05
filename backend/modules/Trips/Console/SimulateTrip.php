<?php

namespace Modules\Trips\Console;

use Illuminate\Console\Command;
use Modules\Access\Models\Account;
use Modules\Subscriptions\Models\Run;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Actions\RecordPings;
use Modules\Trips\Actions\StartTrip;

/**
 * Drives a pretend van along a run's stops, so the rider screen and the map can be watched
 * without anyone leaving the house. Development only.
 */
class SimulateTrip extends Command
{
    protected $signature = 'trips:simulate {run? : Run id. Defaults to the first run that goes today} {--seconds=20 : Seconds between stops}';

    protected $description = 'Drive a pretend van along a run, for trying the live map. Not for production.';

    public function handle(StartTrip $start, RecordPings $record, EndTrip $end): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }

        $run = $this->argument('run')
            ? Run::query()->findOrFail($this->argument('run'))
            : Run::query()->where('is_active', true)->get()->first(fn (Run $run) => $run->runsOn(today()));

        if (! $run) {
            $this->error('No run goes today.');

            return self::FAILURE;
        }

        $driver = Account::query()->findOrFail($run->driver->account_id);
        $trip = $start->handle($run, $driver);
        $stops = $trip->stopsInOrder()->filter(fn ($stop) => $stop->latitude !== null)->values();

        if ($stops->count() < 2) {
            $this->error('Give the route\'s stops a latitude and longitude first (Network > Stops).');

            return self::FAILURE;
        }

        $this->info("Trip started on {$run->label()}. Map: ".url("/t/{$trip->public_id}"));
        $steps = 5;
        $pause = max(1, (int) round((int) $this->option('seconds') / $steps));

        for ($i = 0; $i < $stops->count() - 1; $i++) {
            [$from, $to] = [$stops[$i], $stops[$i + 1]];

            for ($step = 0; $step <= $steps; $step++) {
                $record->handle($trip, $driver, [[
                    'latitude' => $from->latitude + ($to->latitude - $from->latitude) * $step / $steps,
                    'longitude' => $from->longitude + ($to->longitude - $from->longitude) * $step / $steps,
                    'accuracy' => 10,
                    'recorded_at' => now()->toIso8601String(),
                ]]);
                sleep($pause);
            }

            $this->line("Reached {$to->name}.");
        }

        $end->handle($trip, $driver);
        $this->info('Trip ended.');

        return self::SUCCESS;
    }
}
