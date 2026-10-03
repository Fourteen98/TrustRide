<?php

namespace Modules\Subscriptions\Filament\Pages;

use App\Support\Filament\Guarded;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Modules\Subscriptions\Models\Run;
use Modules\Subscriptions\Queries\TodaysRiders as TodaysRidersQuery;
use UnitEnum;

/** The operator's main screen: each run that goes out on the chosen day, with its riders in stop order. */
class TodaysRiders extends Page
{
    protected string $view = 'subscriptions::todays-riders';

    protected static ?string $title = 'Today\'s riders';

    protected static ?string $slug = 'todays-riders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 1;

    #[Url]
    public ?string $date = null;

    public function mount(): void
    {
        $this->date ??= today()->toDateString();
    }

    public static function canAccess(): bool
    {
        return Guarded::can('subscriptions.view');
    }

    public function day(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->date ?: today())->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /** @return Collection<int, array{run: Run, riders: Collection}> */
    public function runs(): Collection
    {
        $day = $this->day();

        return Run::query()
            ->where('is_active', true)
            ->with(['route.stops', 'driver.mates', 'vehicle'])
            ->orderBy('departs_at')
            ->get()
            ->filter(fn (Run $run) => $run->runsOn($day))
            ->map(fn (Run $run) => ['run' => $run, 'riders' => app(TodaysRidersQuery::class)->handle($run, $day)])
            ->values();
    }
}
