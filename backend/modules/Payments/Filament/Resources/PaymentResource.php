<?php

namespace Modules\Payments\Filament\Resources;

use App\Support\Filament\Columns;
use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Filament\Options;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Payments\Actions\ReconcilePayment;
use Modules\Payments\Actions\ResolveManually;
use Modules\Payments\Enums\PaymentStatus;
use Modules\Payments\Filament\Resources\PaymentResource\Pages\ManagePayments;
use Modules\Payments\Models\Payment;
use UnitEnum;

/** Every charge TrustRide has asked for, and what became of it. */
class PaymentResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'payments.view';

    protected static string $managePermission = 'payments.resolve';

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 5;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    /** Payments that have waited longer than a rider needs to approve a prompt. Someone should look. */
    public static function getNavigationBadge(): ?string
    {
        $stuck = Payment::query()
            ->where('status', PaymentStatus::Initiated)
            ->where('created_at', '<=', now()->subMinutes((int) config('payments.fail_after_minutes')))
            ->count();

        return $stuck > 0 ? (string) $stuck : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M, H:i')->sortable(),
                TextColumn::make('description')->label('For')->wrap()->searchable(),
                Columns::money('amount'),
                TextColumn::make('phone')->placeholder('Erased')->searchable(),
                TextColumn::make('network')->formatStateUsing(fn (string $state) => config("payments.networks.{$state}.label", $state)),
                Columns::status('status', ['success' => 'success', 'failed' => 'danger', 'initiated' => 'warning']),
                TextColumn::make('failure_reason')->label('If it failed')->placeholder('')->wrap(),
                TextColumn::make('reference')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_transaction_id')->label('Gateway id')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider')->label('Gateway')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')->label('Paid')->dateTime('j M, H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(PaymentStatus::class)),
            ])
            ->recordActions([
                Action::make('check')
                    ->label('Check status')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Payment $record) => Guarded::can('payments.view') && $record->isWaiting())
                    ->action(function (Payment $record) {
                        $outcome = app(ReconcilePayment::class)->handle($record);

                        Notification::make()->title(match ($outcome) {
                            'paid' => 'The gateway says it is paid.',
                            'failed' => 'The gateway says it was not paid. Marked failed.',
                            'waiting' => 'Not paid yet. She still has time to approve the prompt.',
                            'unknown' => 'The gateway couldn\'t say. Try again later, or check the Hubtel dashboard.',
                            default => 'Already settled.',
                        })->color($outcome === 'paid' ? 'success' : 'warning')->send();
                    }),
                Action::make('resolve')
                    ->label('Settle by hand')->icon(Heroicon::OutlinedHandRaised)->color('warning')
                    ->visible(fn (Payment $record) => Guarded::can('payments.resolve') && $record->status !== PaymentStatus::Success)
                    ->modalDescription('Only after you have seen the money, or seen that it never came, on the Hubtel dashboard. Marking it paid gives the rider what she paid for.')
                    ->schema([
                        Select::make('outcome')->required()->options(['paid' => 'The money arrived', 'failed' => 'The money never came']),
                        Textarea::make('note')->label('How you checked')->required()->maxLength(200),
                    ])
                    ->action(Guarded::calls(fn (Payment $record, array $data) => app(ResolveManually::class)->handle(
                        $record, $data['outcome'] === 'paid', Guarded::staff()->name, $data['note'],
                    )))
                    ->successNotificationTitle('Settled'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
