<?php

namespace App\Admin\Pages;

use App\Admin\Reports\PilotNumbers;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * One page for the question "is the pilot working?". Where things stand now, then the last eight
 * weeks side by side: riders, money, renewals, trips, pickups, support and what messaging cost.
 */
class PilotReport extends Page
{
    protected string $view = 'admin.pilot-report';

    protected static ?string $title = 'Pilot report';

    protected static ?string $slug = 'pilot-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        return Guarded::can('reports.pilot.view');
    }

    public function now(): array
    {
        return app(PilotNumbers::class)->now();
    }

    public function weeks(): Collection
    {
        return app(PilotNumbers::class)->weeks();
    }

    /** "8 of 10 (80%)", or a dash when there was nothing to count. */
    public static function share(int $part, int $whole): string
    {
        return $whole === 0 ? '–' : "{$part} of {$whole} (".round($part / $whole * 100).'%)';
    }
}
