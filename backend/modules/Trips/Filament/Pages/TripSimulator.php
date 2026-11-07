<?php

namespace Modules\Trips\Filament\Pages;

use App\Support\Filament\Guarded;
use BackedEnum;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Modules\Access\Models\Account;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Actions\MarkPickup;
use Modules\Trips\Actions\RecordPings;
use Modules\Trips\Actions\StartTrip;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Models\Trip;
use Modules\Trips\Queries\TripProgress;
use UnitEnum;

/**
 * Drive a pretend van, to see what a rider sees without anyone leaving the house.
 *
 * Pick a run and a rider, start the trip, then put the van at a stop, part of the way to the next
 * one, or anywhere by clicking the map. It goes through the same Actions the crew's phones use, so
 * riders on that run really are told the van has set off, and her app really shows it moving.
 * That is why it is never offered in production.
 */
class TripSimulator extends Page
{
    protected string $view = 'trips::simulator';

    protected static ?string $title = 'Trip simulator';

    protected static ?string $slug = 'trip-simulator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 9;

    #[Url]
    public ?int $runId = null;

    #[Url]
    public ?int $riderId = null;

    /** When the last pretend reading was stamped, in milliseconds. */
    public int $clock = 0;

    public static function canAccess(): bool
    {
        return ! app()->isProduction() && Guarded::can('trips.status.set');
    }

    public function mount(): void
    {
        $this->runId ??= $this->runs()->keys()->first();
    }

    /** @return Collection<int, string> today's runs, id => label */
    public function runs(): Collection
    {
        return Run::query()->where('is_active', true)->with(['route', 'driver'])->orderBy('departs_at')->get()
            ->filter(fn (Run $run) => $run->runsOn(CarbonImmutable::today()))
            ->mapWithKeys(fn (Run $run) => [$run->id => $run->label()]);
    }

    public function run(): ?Run
    {
        return $this->runId ? Run::query()->with(['route.stops', 'driver', 'vehicle'])->find($this->runId) : null;
    }

    public function trip(): ?Trip
    {
        return $this->runId
            ? Trip::query()->where('run_id', $this->runId)->where('date', today()->toDateString())->with(['run.route.stops', 'pickups'])->first()
            : null;
    }

    /** @return Collection<int, array> today's riders on the run who are riding */
    public function riders(): Collection
    {
        $run = $this->run();

        return $run ? app(TodaysRiders::class)->handle($run)->reject(fn (array $rider) => $rider['absent'])->values() : collect();
    }

    public function rider(): ?array
    {
        return $this->riders()->firstWhere('rider_id', $this->riderId);
    }

    /** What the chosen rider's app is showing right now. */
    public function herView(): ?array
    {
        $run = $this->run();

        return $run ? app(TripProgress::class)->handle($run, $this->trip(), $this->rider()['stop_id'] ?? null) : null;
    }

    public function start(): void
    {
        $this->guarded(fn () => app(StartTrip::class)->handle($this->run(), null, Guarded::staff()));
    }

    public function end(): void
    {
        $this->guarded(fn () => ($trip = $this->trip()) && app(EndTrip::class)->handle($trip, null, Guarded::staff()));
    }

    /** Throws today's trip away so it can be started again. Only the simulator can do this. */
    public function restart(): void
    {
        $this->trip()?->delete();
        $this->refreshMap();
    }

    /** Puts the van at a stop, or $percent of the way from it to the next stop. */
    public function placeAtStop(int $stopId, int $percent = 0): void
    {
        $this->guarded(function () use ($stopId, $percent) {
            $trip = $this->trip() ?? throw new DomainException('Start the trip first.');
            $stops = $trip->stopsInOrder();
            $index = $trip->orderOf($stopId) ?? throw new DomainException('That stop is not on this run.');
            $from = $stops[$index];
            $to = $stops[$index + 1] ?? $from;

            if ($from->latitude === null || $to->latitude === null) {
                throw new DomainException("Give {$from->name} and the stop after it a latitude and longitude first, under Network > Stops.");
            }

            // To be past a stop the van must first have been at it.
            $this->ping($trip, $from->latitude, $from->longitude);

            $share = max(0, min(100, $percent)) / 100;
            $this->ping($trip, $from->latitude + ($to->latitude - $from->latitude) * $share, $from->longitude + ($to->longitude - $from->longitude) * $share);
        });
    }

    /** Puts the van anywhere. The map calls this when it is clicked. */
    public function placeVan(float $latitude, float $longitude): void
    {
        $this->guarded(fn () => $this->ping($this->trip() ?? throw new DomainException('Start the trip first.'), $latitude, $longitude));
    }

    public function mark(string $status): void
    {
        $this->guarded(function () use ($status) {
            $trip = $this->trip() ?? throw new DomainException('Start the trip first.');
            $rider = $this->rider() ?? throw new DomainException('Pick a rider first.');

            app(MarkPickup::class)->handle($trip, $rider['rider_id'], PickupStatus::from($status));
        });
    }

    private function ping(Trip $trip, float $latitude, float $longitude): void
    {
        // Sent as the driver's phone would send it.
        $driver = Account::query()->findOrFail($trip->run->driver->account_id);

        // Each reading is stamped a moment after the last, so two sent together still arrive in order.
        $this->clock = max($this->clock + 1, now()->getTimestampMs());

        app(RecordPings::class)->handle($trip, $driver, [[
            'latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 10,
            'recorded_at' => CarbonImmutable::createFromTimestampMs($this->clock)->toIso8601ZuluString('millisecond'),
        ]]);
    }

    /** Runs a step, shows a module's refusal as a notice, and tells the map to look again at once. */
    private function guarded(callable $step): void
    {
        try {
            $step();
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }

        $this->refreshMap();
    }

    private function refreshMap(): void
    {
        $this->dispatch('aveo-refresh-map');
    }
}
