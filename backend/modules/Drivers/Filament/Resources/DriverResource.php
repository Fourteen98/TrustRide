<?php

namespace Modules\Drivers\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\MoneyInput;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Modules\Drivers\Actions\ApproveDriver;
use Modules\Drivers\Actions\RecordAssessment;
use Modules\Drivers\Actions\RegisterMate;
use Modules\Drivers\Actions\SuspendDriver;
use Modules\Drivers\Enums\AssessmentOutcome;
use Modules\Drivers\Enums\DriverStatus;
use Modules\Drivers\Filament\Resources\DriverResource\Pages\ManageDrivers;
use Modules\Drivers\Filament\VettingActions;
use Modules\Drivers\Models\Driver;
use UnitEnum;

class DriverResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'drivers.view';

    protected static string $managePermission = 'drivers.manage';

    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Drivers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('phone')->tel()->required()
                ->helperText('He logs in to the app with this number.')
                ->disabledOn('edit'),
            TextInput::make('licence_number')->maxLength(50),
            DatePicker::make('licence_expires_on')->label('Licence expires'),
            TextInput::make('ghana_card_number')->maxLength(30),
            MoneyInput::make('monthly_commission')->label('Monthly commission')
                ->helperText('What TrustRide pays him each month for holding seats.'),
            TextInput::make('next_of_kin_name')->maxLength(100),
            TextInput::make('next_of_kin_phone')->tel()->maxLength(20),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (DriverStatus $state) => $state->label())
                    ->color(fn (DriverStatus $state) => match ($state) {
                        DriverStatus::Approved => 'success',
                        DriverStatus::Suspended => 'danger',
                        DriverStatus::Pending => 'warning',
                    }),
                TextColumn::make('monthly_commission')->label('Commission')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('vehicles.registration_number')->label('Vehicles')->placeholder('None'),
                TextColumn::make('mates.name')->label('Mates')->placeholder('None'),
            ])
            ->recordActions([
                VettingActions::checklist(),
                VettingActions::override(),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Driver $record) => Guarded::can('drivers.vet') && ! $record->isApproved())
                    ->requiresConfirmation()
                    ->action(fn (Action $action, Driver $record) => Guarded::run($action, fn () => app(ApproveDriver::class)->handle($record, Guarded::staff())))
                    ->successNotificationTitle('Driver approved'),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('addMate')
                        ->label('Add a mate')->icon(Heroicon::OutlinedUserPlus)
                        ->visible(fn () => Guarded::can('drivers.manage'))
                        ->schema([
                            TextInput::make('name')->required()->maxLength(100),
                            TextInput::make('phone')->tel()->required()->helperText('He logs in to the app with this number.'),
                        ])
                        ->action(fn (Action $action, Driver $record, array $data) => Guarded::run($action, fn () => app(RegisterMate::class)->handle($record, $data['name'], $data['phone'])))
                        ->successNotificationTitle('Mate added'),
                    Action::make('assess')
                        ->label('Assess a month')->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->visible(fn () => Guarded::can('drivers.assess'))
                        ->schema([
                            DatePicker::make('month')->label('Any day in the month')->required()->default(fn () => now()->subMonthNoOverflow()->startOfMonth()),
                            Select::make('outcome')->required()
                                ->options(collect(AssessmentOutcome::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                            Textarea::make('notes')->rows(3),
                            MoneyInput::make('bonus')->default(0),
                        ])
                        ->action(fn (Action $action, Driver $record, array $data) => Guarded::run($action, fn () => app(RecordAssessment::class)->handle(
                            $record, Carbon::parse($data['month']), AssessmentOutcome::from($data['outcome']), Guarded::staff(), $data['notes'] ?? null, (int) $data['bonus'],
                        )))
                        ->successNotificationTitle('Assessment recorded'),
                    Action::make('suspend')
                        ->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (Driver $record) => Guarded::can('drivers.manage') && $record->status !== DriverStatus::Suspended)
                        ->schema([Textarea::make('reason')->required()->rows(3)])
                        ->action(fn (Action $action, Driver $record, array $data) => Guarded::run($action, fn () => app(SuspendDriver::class)->handle($record, $data['reason'])))
                        ->successNotificationTitle('Driver suspended'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDrivers::route('/')];
    }
}
