<?php

namespace Modules\Riders\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Riders\Actions\EraseRider;
use Modules\Riders\Filament\Resources\DeletionRequestResource\Pages\ManageDeletionRequests;
use Modules\Riders\Models\DataDeletionRequest;
use UnitEnum;

/**
 * Riders who asked for their data to be deleted. An operator with the right permission confirms each one.
 * Nothing is created or edited here. The only action is Erase.
 */
class DeletionRequestResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'riders.data.erase';

    protected static string $managePermission = 'riders.data.erase';

    protected static ?string $model = DataDeletionRequest::class;

    protected static ?string $modelLabel = 'deletion request';

    protected static ?string $slug = 'deletion-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Riders';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = DataDeletionRequest::query()->where('status', DataDeletionRequest::PENDING)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at')
            ->columns([
                TextColumn::make('rider.name')->label('Rider')->placeholder('Deleted'),
                TextColumn::make('rider.account.phone')->label('Phone')->placeholder('Deleted'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        DataDeletionRequest::PENDING => 'warning',
                        DataDeletionRequest::COMPLETED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('requested_at')->label('Asked')->since()->sortable(),
                TextColumn::make('completed_at')->label('Deleted on')->date()->placeholder('Not yet'),
                TextColumn::make('completer.name')->label('Deleted by')->placeholder('Nobody yet'),
            ])
            ->recordActions([
                Action::make('erase')
                    ->label('Delete her data')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn (DataDeletionRequest $record) => $record->isPending() && $record->rider_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (DataDeletionRequest $record) => "Delete {$record->rider?->name} for good?")
                    ->modalDescription('This removes her applications, seat, subscriptions and messages, and her login. It ends any running subscription and frees her seat. It cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete her data')
                    ->action(fn (Action $action, DataDeletionRequest $record) => Guarded::run($action, fn () => app(EraseRider::class)->handle($record, Guarded::staff())))
                    ->successNotificationTitle('Her data has been deleted'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeletionRequests::route('/')];
    }
}
