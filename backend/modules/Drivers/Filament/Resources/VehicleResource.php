<?php

namespace Modules\Drivers\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Drivers\Actions\ApproveVehicle;
use Modules\Drivers\Enums\ExtraSeats;
use Modules\Drivers\Enums\VehicleOwnership;
use Modules\Drivers\Filament\Resources\VehicleResource\Pages\ManageVehicles;
use Modules\Drivers\Filament\VettingActions;
use Modules\Drivers\Models\Driver;
use Modules\Drivers\Models\Vehicle;
use UnitEnum;

class VehicleResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'drivers.view';

    protected static string $managePermission = 'drivers.manage';

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Drivers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('driver_id')->label('Driver')->required()->searchable()
                ->options(fn () => Driver::query()->orderBy('name')->pluck('name', 'id')),
            TextInput::make('registration_number')->label('Car number')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('make')->maxLength(60)->placeholder('Toyota Hiace'),
            TextInput::make('colour')->required()->maxLength(40),
            TextInput::make('seats_total')->label('Total seats')->numeric()->required()->minValue(1)->maxValue(60),
            Select::make('ownership')->label('Owned by')->options(Options::enum(VehicleOwnership::class))->required()->default('own')->live(),
            TextInput::make('owner_name')->label('Partner')->maxLength(100)->placeholder('Who owns the van')
                ->visible(fn ($get) => $get('ownership') === VehicleOwnership::Partner->value || $get('ownership') === VehicleOwnership::Partner),
            Select::make('extra_seats')->label('Riders may hold seats for people with them')->options(Options::enum(ExtraSeats::class))->required()->default('off')
                ->helperText('"Pay the fare": she holds seats for a day and each person pays the mate. "Added to her plan": she pays for them with her plan, every day.'),
            Textarea::make('condition_note')->label('Condition')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration_number')->label('Car number')->searchable()->sortable(),
                TextColumn::make('driver.name')->searchable(),
                TextColumn::make('make')->placeholder('Not given'),
                TextColumn::make('colour'),
                TextColumn::make('seats_total')->label('Seats'),
                TextColumn::make('ownership')->label('Owned by')->formatStateUsing(fn (Vehicle $record) => $record->ownership === VehicleOwnership::Partner ? ($record->owner_name ?: 'Partner') : 'Aveo'),
                TextColumn::make('extra_seats')->label('Extra seats')->formatStateUsing(fn (Vehicle $record) => $record->extra_seats->label()),
                Columns::status('status', ['approved' => 'success', 'off_road' => 'danger', 'pending' => 'warning']),
            ])
            ->recordActions([
                VettingActions::checklist(),
                VettingActions::override(),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Vehicle $record) => Guarded::can('drivers.vet') && ! $record->isApproved())
                    ->requiresConfirmation()
                    ->action(Guarded::calls(fn (Vehicle $record) => app(ApproveVehicle::class)->handle($record)))
                    ->successNotificationTitle('Vehicle approved'),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVehicles::route('/')];
    }
}
