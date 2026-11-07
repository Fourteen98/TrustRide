<?php

namespace Modules\Subscriptions\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Enums\VehicleStatus;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use Modules\Network\Models\Route;
use Modules\Subscriptions\Actions\AnnounceToRun;
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
                ->options(Options::enum(Direction::class)),
            Select::make('driver_id')->label('Driver')->required()->live()
                ->helperText('Only approved drivers are listed.')
                ->options(fn () => Driver::query()->where('status', DriverStatus::Approved)->orderBy('name')->pluck('name', 'id')),
            Select::make('vehicle_id')->label('Vehicle')->required()
                ->helperText('Only this driver\'s approved vehicles are listed.')
                ->options(fn (Get $get) => Vehicle::query()
                    ->where('driver_id', $get('driver_id'))->where('status', VehicleStatus::Approved)
                    ->get()->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => "{$vehicle->registration_number} ({$vehicle->colour}, {$vehicle->seats_total} seats)"])),
            TimePicker::make('departs_at')->label('Leaves the first stop at')->required()->seconds(false),
            TextInput::make('seats_held')->label('Seats held for Aveo')->numeric()->required()->minValue(1),
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
                TextColumn::make('taken_seats_sum_party')->sum('takenSeats', 'party')->label('Taken')->default(0),
                TextColumn::make('free')->label('Free')->badge()
                    ->state(fn (Run $record) => max(0, $record->seats_held - (int) $record->taken_seats_sum_party))
                    ->color(fn (int $state) => $state === 0 ? 'danger' : ($state <= 2 ? 'warning' : 'success')),
                IconColumn::make('is_active')->label('Running')->boolean(),
            ])
            ->recordActions([
                Action::make('announce')
                    ->label('Message riders')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn () => Guarded::can('messaging.broadcast'))
                    ->modalHeading(fn (Run $record) => "Message the riders on the {$record->departureTime()} run")
                    ->modalDescription('Goes to every rider expected on this run that day, on her preferred channel. Riders who said they won\'t ride are left out.')
                    ->schema([
                        DatePicker::make('date')->required()->default(fn () => today()),
                        Textarea::make('message')->required()->rows(3)->maxLength(300)
                            ->placeholder('The van is running 15 minutes late this morning. Sorry.'),
                    ])
                    ->action(Guarded::calls(function (Run $record, array $data) {
                        $sent = app(AnnounceToRun::class)->handle($record, $data['message'], Guarded::staff(), Carbon::parse($data['date']));

                        Notification::make()->success()->title("Sent to {$sent} ".Str::plural('rider', $sent))->send();
                    })),
                EditAction::make()->using(Guarded::calls(fn (Run $record, array $data) => app(SaveRun::class)->handle($data, $record))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRuns::route('/')];
    }
}
