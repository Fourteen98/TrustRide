<?php

namespace Modules\Subscriptions\Filament\Resources;

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
use Modules\Subscriptions\Actions\StopAutoRenewal;
use Modules\Subscriptions\Enums\AutoRenewalStatus;
use Modules\Subscriptions\Filament\Resources\AutoRenewalResource\Pages\ManageAutoRenewals;
use Modules\Subscriptions\Models\AutoRenewal;
use UnitEnum;

/** Riders who pay by direct debit. An operator can turn one off when she asks by phone. Only she can turn it on. */
class AutoRenewalResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'subscriptions.view';

    protected static string $managePermission = 'subscriptions.activate';

    protected static ?string $model = AutoRenewal::class;

    protected static ?string $modelLabel = 'automatic renewal';

    protected static ?string $slug = 'auto-renewals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 6;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('rider.name')->label('Rider')->searchable(),
                TextColumn::make('plan.name')->label('Plan'),
                Columns::money('plan.price')->label('Takes'),
                TextColumn::make('mandate.network')->label('From')->formatStateUsing(fn (?string $state) => config("payments.networks.{$state}.label", $state)),
                TextColumn::make('mandate.phone')->label('Wallet')->placeholder('Erased'),
                Columns::status('status', ['active' => 'success', 'pending' => 'warning', 'off' => 'gray']),
                TextColumn::make('failures')->label('Failed in a row')->color(fn (int $state) => $state > 0 ? 'danger' : null),
                TextColumn::make('last_error')->label('Last problem')->placeholder('')->wrap(),
                TextColumn::make('last_attempt_on')->label('Last tried')->date()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(AutoRenewalStatus::class)),
            ])
            ->recordActions([
                Action::make('stop')
                    ->label('Turn off')->icon(Heroicon::OutlinedNoSymbol)->color('gray')
                    ->visible(fn (AutoRenewal $record) => Guarded::can('subscriptions.activate') && $record->status !== AutoRenewalStatus::Off)
                    ->requiresConfirmation()
                    ->modalDescription('The direct debit is withdrawn and nothing more is taken. Her running subscription stays as it is.')
                    ->action(Guarded::calls(fn (AutoRenewal $record) => app(StopAutoRenewal::class)->handle($record)))
                    ->successNotificationTitle('Turned off'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAutoRenewals::route('/')];
    }
}
