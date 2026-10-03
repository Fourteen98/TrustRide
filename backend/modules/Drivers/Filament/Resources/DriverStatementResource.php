<?php

namespace Modules\Drivers\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Drivers\Actions\MarkStatementPaid;
use Modules\Drivers\Filament\Resources\DriverStatementResource\Pages\ManageDriverStatements;
use Modules\Drivers\Models\DriverStatement;
use UnitEnum;

/**
 * Each driver's month: commission, assessment and whether he has been paid.
 * A statement is opened by "Assess a month" on the driver. Nothing is created here.
 */
class DriverStatementResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'drivers.view';

    protected static string $managePermission = 'drivers.statements.pay';

    protected static ?string $model = DriverStatement::class;

    protected static ?string $modelLabel = 'monthly statement';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Drivers';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('driver.name')->searchable(),
                TextColumn::make('period')->label('Month')->formatStateUsing(fn ($state) => $state->format('F Y'))->sortable(),
                TextColumn::make('commission_amount')->label('Commission')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('bonus_amount')->label('Bonus')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('total')->state(fn (DriverStatement $record) => Money::format($record->total())),
                TextColumn::make('assessment_outcome')->label('Assessment')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())->placeholder('Not assessed'),
                TextColumn::make('paid_at')->label('Paid')->date()->placeholder('Not paid'),
                TextColumn::make('payment_reference')->label('Reference')->placeholder('None'),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->visible(fn (DriverStatement $record) => Guarded::can('drivers.statements.pay') && ! $record->isPaid())
                    ->modalDescription('Pay the driver first, outside the system. This only records that it happened.')
                    ->schema([TextInput::make('reference')->label('Payment reference')->required()->maxLength(100)])
                    ->action(fn (Action $action, DriverStatement $record, array $data) => Guarded::run($action, fn () => app(MarkStatementPaid::class)->handle($record, Guarded::staff(), $data['reference'])))
                    ->successNotificationTitle('Marked as paid'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDriverStatements::route('/')];
    }
}
