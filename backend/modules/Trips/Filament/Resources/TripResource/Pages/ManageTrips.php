<?php

namespace Modules\Trips\Filament\Resources\TripResource\Pages;

use App\Support\Filament\Guarded;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Modules\Subscriptions\Models\Run;
use Modules\Trips\Actions\StartTrip;
use Modules\Trips\Filament\Resources\TripResource;

class ManageTrips extends ManageRecords
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Start a trip by hand')
                ->visible(fn () => Guarded::can('trips.status.set'))
                ->modalDescription('For when the crew\'s app is off and the mate has called to say they have left. Riders are told the van has set off.')
                ->schema([
                    Select::make('run_id')->label('Run')->required()
                        ->options(fn () => Run::query()->where('is_active', true)->with(['route', 'driver'])->orderBy('departs_at')->get()
                            ->filter(fn (Run $run) => $run->runsOn(CarbonImmutable::today()))
                            ->mapWithKeys(fn (Run $run) => [$run->id => $run->label()])),
                ])
                ->action(Guarded::calls(fn (array $data) => app(StartTrip::class)->handle(Run::query()->findOrFail($data['run_id']), null, Guarded::staff())))
                ->successNotificationTitle('Trip started'),
        ];
    }
}
