<?php

namespace Modules\Subscriptions\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Riders\Actions\RejectApplication;
use Modules\Riders\Enums\ApplicationStatus;
use Modules\Riders\Enums\RejectionReason;
use Modules\Riders\Models\RiderApplication;
use Modules\Subscriptions\Actions\ApproveApplicationWithSeats;
use Modules\Subscriptions\Filament\Resources\ApplicationResource\Pages\ManageApplications;
use Modules\Subscriptions\Models\Run;
use UnitEnum;

/**
 * Reviewing applications. It lives in Subscriptions, not Riders, because approving means reserving a seat,
 * and only Subscriptions knows about runs and seats.
 */
class ApplicationResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'riders.view';

    protected static string $managePermission = 'riders.review';

    protected static ?string $model = RiderApplication::class;

    protected static ?string $modelLabel = 'application';

    protected static ?string $slug = 'applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Riders';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = RiderApplication::query()->where('status', ApplicationStatus::Pending)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('rider.name')->label('Rider')->searchable(),
                TextColumn::make('rider.account.phone')->label('Phone')->searchable(),
                TextColumn::make('route.name')->label('Route'),
                TextColumn::make('stop.name')->label('Stop'),
                TextColumn::make('morning_time')->label('Morning')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5))->placeholder('No'),
                TextColumn::make('evening_time')->label('Evening')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5))->placeholder('No'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state) => $state->label())
                    ->color(fn (ApplicationStatus $state) => match ($state) {
                        ApplicationStatus::Approved => 'success',
                        ApplicationStatus::Rejected => 'danger',
                        ApplicationStatus::Pending => 'warning',
                        ApplicationStatus::Withdrawn => 'gray',
                    }),
                TextColumn::make('rejection_reason')->label('Reason')->formatStateUsing(fn (?RejectionReason $state) => $state?->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Applied')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(ApplicationStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (RiderApplication $record) => Guarded::can('riders.review') && $record->isPending())
                    ->modalHeading(fn (RiderApplication $record) => "Approve {$record->rider->name}")
                    ->modalDescription('Choose the run that will carry her. That reserves her seat. Choose one in each direction if she rides both ways.')
                    ->schema(fn (RiderApplication $record) => [
                        CheckboxList::make('run_ids')->label('Runs on this route')->required()
                            ->options(Run::query()
                                ->where('route_id', $record->route_id)->where('is_active', true)
                                ->with(['driver', 'vehicle'])->orderBy('departs_at')->get()
                                ->mapWithKeys(fn (Run $run) => [$run->id => sprintf(
                                    '%s %s, %s, %s. %d of %d seats free',
                                    $run->direction->label(), $run->departureTime(), $run->driver->name,
                                    $run->vehicle->registration_number, $run->seatsFree(), $run->seats_held,
                                )])),
                    ])
                    ->action(fn (Action $action, RiderApplication $record, array $data) => Guarded::run($action, fn () => app(ApproveApplicationWithSeats::class)->handle(
                        $record, array_map('intval', $data['run_ids']), Guarded::staff(),
                    )))
                    ->successNotificationTitle('Approved, and her seat is reserved'),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (RiderApplication $record) => Guarded::can('riders.review') && $record->isPending())
                    ->modalDescription('A rejection is about what we can serve, not about her. She is told the reason.')
                    ->schema([
                        Select::make('reason')->required()
                            ->options(collect(RejectionReason::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                        Textarea::make('note')->rows(2),
                    ])
                    ->action(fn (Action $action, RiderApplication $record, array $data) => Guarded::run($action, fn () => app(RejectApplication::class)->handle(
                        $record, Guarded::staff(), RejectionReason::from($data['reason']), $data['note'] ?? null,
                    )))
                    ->successNotificationTitle('Application rejected'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApplications::route('/')];
    }
}
