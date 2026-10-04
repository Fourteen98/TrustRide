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
use Modules\Subscriptions\Actions\SettlePlanPurchase;
use Modules\Subscriptions\Enums\PurchaseStatus;
use Modules\Subscriptions\Filament\Resources\PlanPurchaseResource\Pages\ManagePlanPurchases;
use Modules\Subscriptions\Models\PlanPurchase;
use UnitEnum;

/** Riders buying plans in the app. The ones that need a person are counted on the menu. */
class PlanPurchaseResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'subscriptions.view';

    protected static string $managePermission = 'subscriptions.activate';

    protected static ?string $model = PlanPurchase::class;

    protected static ?string $modelLabel = 'purchase';

    protected static ?string $slug = 'purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 4;

    protected static function isReadOnly(): bool
    {
        return true;
    }

    public static function getNavigationBadge(): ?string
    {
        $stuck = PlanPurchase::query()->where('status', PurchaseStatus::NeedsAttention)->count();

        return $stuck > 0 ? (string) $stuck : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M, H:i')->sortable(),
                TextColumn::make('rider.name')->label('Rider')->searchable(),
                TextColumn::make('rider.account.phone')->label('Phone')->searchable(),
                TextColumn::make('plan.name')->label('Plan'),
                Columns::money('amount'),
                Columns::status('status', ['activated' => 'success', 'failed' => 'gray', 'pending' => 'warning', 'needs_attention' => 'danger']),
                TextColumn::make('problem')->placeholder('')->wrap(),
                TextColumn::make('payment.reference')->label('Payment')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Options::enum(PurchaseStatus::class)),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Try to activate again')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (PlanPurchase $record) => Guarded::can('subscriptions.activate') && $record->status === PurchaseStatus::NeedsAttention)
                    ->requiresConfirmation()
                    ->modalDescription('She has paid. Fix what the problem column says first (give her a seat, or put the plan back on sale), then try again.')
                    ->action(function (PlanPurchase $record, Action $action) {
                        $purchase = app(SettlePlanPurchase::class)->paid($record, Guarded::staff());

                        $purchase->status === PurchaseStatus::Activated
                            ? $action->success()
                            : $action->failureNotificationTitle($purchase->problem)->failure();
                    })
                    ->successNotificationTitle('Subscription active'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlanPurchases::route('/')];
    }
}
