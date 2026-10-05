<?php

namespace Modules\Trips\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Trips\Actions\EndTrip;
use Modules\Trips\Enums\PickupStatus;
use Modules\Trips\Enums\TripStatus;
use Modules\Trips\Filament\Resources\TripResource\Pages\ManageTrips;
use Modules\Trips\Models\Trip;
use UnitEnum;

/** Every trip, newest first. The ones on the road now are counted on the menu, and each has a live map. */
class TripResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'trips.view';

    protected static string $managePermission = 'trips.status.set';

    protected static ?string $model = Trip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 0;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        $running = Trip::query()->where('status', TripStatus::Running)->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function table(Table $table): Table
    {
        $count = fn (PickupStatus $status) => fn (Builder $query) => $query->where('status', $status);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['run.route', 'run.driver', 'run.vehicle', 'reachedStop'])->withCount([
                'pickups as picked_up_count' => $count(PickupStatus::PickedUp),
                'pickups as missed_count' => $count(PickupStatus::Missed),
            ]))
            ->defaultSort('started_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('date')->date('D j M')->sortable(),
                TextColumn::make('run')->label('Run')->state(fn (Trip $record) => $record->run->label()),
                TextColumn::make('run.vehicle.registration_number')->label('Car'),
                Columns::status('status', ['running' => 'success', 'ended' => 'gray']),
                TextColumn::make('started_at')->label('Left')->dateTime('H:i'),
                TextColumn::make('ended_at')->label('Arrived')->dateTime('H:i')->placeholder(''),
                TextColumn::make('reachedStop.name')->label('Last stop reached')->placeholder('None yet'),
                TextColumn::make('picked_up_count')->label('On board'),
                TextColumn::make('missed_count')->label('Not at stop'),
                TextColumn::make('located_at')->label('Position')->since()->placeholder('None sent')
                    ->color(fn (Trip $record) => $record->isRunning() && ! $record->hasFreshPosition() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(TripStatus::class)),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Live map')->icon(Heroicon::OutlinedMap)
                    ->url(fn (Trip $record) => url("/t/{$record->public_id}"))->openUrlInNewTab(),
                Action::make('end')
                    ->label('End by hand')->icon(Heroicon::OutlinedStopCircle)->color('gray')
                    ->visible(fn (Trip $record) => Guarded::can('trips.status.set') && $record->isRunning())
                    ->requiresConfirmation()
                    ->modalDescription('For when the crew forgot to end it, or their app is off. Riders stop seeing the van.')
                    ->action(Guarded::calls(fn (Trip $record) => app(EndTrip::class)->handle($record, null, Guarded::staff())))
                    ->successNotificationTitle('Trip ended'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrips::route('/')];
    }
}
