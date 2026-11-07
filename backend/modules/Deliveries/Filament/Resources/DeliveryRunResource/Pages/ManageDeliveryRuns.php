<?php

namespace Modules\Deliveries\Filament\Resources\DeliveryRunResource\Pages;

use App\Support\Filament\Guarded;
use App\Support\Filament\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Modules\Deliveries\Actions\BuildRun;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Filament\Resources\DeliveryRunResource;
use Modules\Deliveries\Models\Zone;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\WorkType;
use Modules\Drivers\Models\Driver;

class ManageDeliveryRuns extends ManageRecords
{
    protected static string $resource = DeliveryRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('build')->label('Build a run')->icon(Heroicon::OutlinedPlus)
                ->visible(fn () => Guarded::can('deliveries.runs.manage'))
                ->modalDescription('Every parcel waiting for the zone on that day goes on the run, grouped by business so he collects each shop\'s together.')
                ->schema([
                    DatePicker::make('date')->required()->default(today())->live(),
                    Select::make('zone_id')->label('Zone')->required()
                        ->options(fn (Get $get) => Zone::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(function (Zone $zone) use ($get) {
                            $waiting = $zone->deliveries()->whereDate('delivery_date', Carbon::parse($get('date') ?? today()))->where('status', DeliveryStatus::Pending)->count();

                            return [$zone->id => "{$zone->name}: {$waiting} waiting, cut-off {$zone->cutoff_time}"];
                        })),
                    Select::make('driver_id')->label('Driver')->required()
                        ->options(fn () => Driver::query()->where('status', DriverStatus::Approved)->orderBy('name')->get()
                            ->filter(fn (Driver $driver) => $driver->does(WorkType::Ox))->pluck('name', 'id'))
                        ->helperText('Approved drivers who do Ox work.'),
                    MoneyInput::make('driver_pay')->label('His pay for the run')->required()->default((int) config('deliveries.default_run_pay')),
                ])
                ->action(Guarded::calls(fn (array $data) => app(BuildRun::class)->handle(
                    Zone::findOrFail($data['zone_id']), Carbon::parse($data['date']), Driver::findOrFail($data['driver_id']), Guarded::staff(), (int) $data['driver_pay'],
                )))
                ->successNotificationTitle('Run built. The driver sees it in the app.'),
        ];
    }
}
