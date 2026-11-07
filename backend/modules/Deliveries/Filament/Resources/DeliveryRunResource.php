<?php

namespace Modules\Deliveries\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Deliveries\Actions\FinishRun;
use Modules\Deliveries\Actions\StartRun;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Deliveries\Filament\Resources\DeliveryRunResource\Pages\ManageDeliveryRuns;
use Modules\Deliveries\Models\DeliveryRun;
use UnitEnum;

/** The batches: one driver, one zone, one day. Built here by an operator; driven from the app. */
class DeliveryRunResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'deliveries.view';

    protected static string $managePermission = 'deliveries.runs.manage';

    protected static ?string $model = DeliveryRun::class;

    protected static ?string $modelLabel = 'run';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Ox';

    protected static ?int $navigationSort = 2;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('zone', 'driver')->withCount([
                'deliveries',
                'deliveries as delivered_count' => fn ($query) => $query->where('status', DeliveryStatus::Delivered),
                'deliveries as failed_count' => fn ($query) => $query->where('status', DeliveryStatus::Failed),
            ]))
            ->columns([
                TextColumn::make('date')->date('D j M')->sortable(),
                TextColumn::make('zone.name')->label('Zone'),
                TextColumn::make('driver.name')->label('Driver'),
                Columns::status('status', ['planned' => 'gray', 'out' => 'warning', 'done' => 'success']),
                TextColumn::make('deliveries_count')->label('Parcels')
                    ->formatStateUsing(fn (DeliveryRun $record) => "{$record->delivered_count} of {$record->deliveries_count} delivered".($record->failed_count ? ", {$record->failed_count} not" : '')),
                Columns::money('driver_pay')->label('Driver\'s pay'),
            ])
            ->recordActions([
                Action::make('parcels')->icon(Heroicon::OutlinedCube)->color('gray')
                    ->url(fn (DeliveryRun $record) => DeliveryResource::getUrl('index', ['filters' => ['delivery_run_id' => ['value' => $record->id], 'day' => ['day' => null]]])),
                Action::make('start')->label('Start by hand')->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (DeliveryRun $record) => Guarded::can('deliveries.runs.manage') && $record->status === RunStatus::Planned)
                    ->requiresConfirmation()->modalDescription('For when the driver\'s phone is off. He can then collect parcels.')
                    ->action(Guarded::calls(fn (DeliveryRun $record) => app(StartRun::class)->handle($record)))
                    ->successNotificationTitle('Run started'),
                Action::make('finish')->label('Finish by hand')->icon(Heroicon::OutlinedFlag)
                    ->visible(fn (DeliveryRun $record) => Guarded::can('deliveries.runs.manage') && $record->status === RunStatus::Out)
                    ->requiresConfirmation()->modalDescription('Parcels he never collected go back in the queue for tomorrow.')
                    ->action(Guarded::calls(fn (DeliveryRun $record) => app(FinishRun::class)->handle($record)))
                    ->successNotificationTitle('Run finished'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryRuns::route('/')];
    }
}
