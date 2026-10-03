<?php

namespace Modules\Drivers\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
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
use Modules\Drivers\Enums\VehicleStatus;
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
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (VehicleStatus $state) => $state->label())
                    ->color(fn (VehicleStatus $state) => match ($state) {
                        VehicleStatus::Approved => 'success',
                        VehicleStatus::OffRoad => 'danger',
                        VehicleStatus::Pending => 'warning',
                    }),
            ])
            ->recordActions([
                VettingActions::checklist(),
                VettingActions::override(),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Vehicle $record) => Guarded::can('drivers.vet') && ! $record->isApproved())
                    ->requiresConfirmation()
                    ->action(fn (Action $action, Vehicle $record) => Guarded::run($action, fn () => app(ApproveVehicle::class)->handle($record)))
                    ->successNotificationTitle('Vehicle approved'),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVehicles::route('/')];
    }
}
