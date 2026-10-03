<?php

namespace Modules\Subscriptions\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\VehicleStatus;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Network\Models\Route;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Enums\Direction;
use Modules\Subscriptions\Filament\Resources\RunResource\Pages\ManageRuns;
use Modules\Subscriptions\Models\Run;
use UnitEnum;

/** Runs, and how full each one is. This is the capacity view: seats held, taken and free per route and time. */
class RunResource extends Resource
{
    use GatedByPermission;

    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    protected static string $viewPermission = 'subscriptions.view';

    protected static string $managePermission = 'subscriptions.runs.manage';

    protected static ?string $model = Run::class;

    protected static ?string $navigationLabel = 'Runs and capacity';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('route_id')->label('Route')->required()
                ->options(fn () => Route::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            Select::make('direction')->required()->default(Direction::Outbound->value)
                ->options(collect(Direction::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            Select::make('driver_id')->label('Driver')->required()->live()
                ->helperText('Only approved drivers are listed.')
                ->options(fn () => Driver::query()->where('status', DriverStatus::Approved)->orderBy('name')->pluck('name', 'id')),
            Select::make('vehicle_id')->label('Vehicle')->required()
                ->helperText('Only this driver\'s approved vehicles are listed.')
                ->options(fn (Get $get) => Vehicle::query()
                    ->where('driver_id', $get('driver_id'))->where('status', VehicleStatus::Approved)
                    ->get()->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => "{$vehicle->registration_number} ({$vehicle->colour}, {$vehicle->seats_total} seats)"])),
            TimePicker::make('departs_at')->label('Leaves the first stop at')->required()->seconds(false),
            TextInput::make('seats_held')->label('Seats held for TrustRide')->numeric()->required()->minValue(1),
            CheckboxList::make('days_of_week')->label('Days')->options(self::DAYS)->columns(7)->required()
                ->default([1, 2, 3, 4, 5])->columnSpanFull(),
            Toggle::make('is_active')->label('Running')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('departs_at')
            ->columns([
                TextColumn::make('route.name')->searchable()->sortable(),
                TextColumn::make('direction')->formatStateUsing(fn (Direction $state) => $state === Direction::Outbound ? 'To' : 'Back'),
                TextColumn::make('departs_at')->label('Leaves')->formatStateUsing(fn (Run $record) => $record->departureTime())->sortable(),
                TextColumn::make('days_of_week')->label('Days')
                    ->state(fn (Run $record) => collect($record->days_of_week)->map(fn ($day) => self::DAYS[$day] ?? $day)->join(' ')),
                TextColumn::make('driver.name')->searchable(),
                TextColumn::make('vehicle.registration_number')->label('Car'),
                TextColumn::make('seats_held')->label('Held'),
                TextColumn::make('taken_seats_count')->counts('takenSeats')->label('Taken'),
                TextColumn::make('free')->label('Free')->badge()
                    ->state(fn (Run $record) => max(0, $record->seats_held - $record->taken_seats_count))
                    ->color(fn (int $state) => $state === 0 ? 'danger' : ($state <= 2 ? 'warning' : 'success')),
                IconColumn::make('is_active')->label('Running')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Action $action, Run $record, array $data) => Guarded::run($action, fn () => app(SaveRun::class)->handle($data, $record))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRuns::route('/')];
    }
}
