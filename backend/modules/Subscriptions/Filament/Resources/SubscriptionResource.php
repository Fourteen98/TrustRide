<?php

namespace Modules\Subscriptions\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use App\Support\Filament\Guarded;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Actions\GiveAbsenceNotice;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Filament\Resources\SubscriptionResource\Pages\ManageSubscriptions;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use UnitEnum;

class SubscriptionResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'subscriptions.view';

    protected static string $managePermission = 'subscriptions.activate';

    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function planOptions()
    {
        return Plan::query()->where('is_active', true)->orderBy('duration_days')->get()
            ->mapWithKeys(fn (Plan $plan) => [$plan->id => "{$plan->name}, ".Money::format($plan->price)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_on', 'desc')
            ->columns([
                TextColumn::make('rider.name')->label('Rider')->searchable(),
                TextColumn::make('rider.account.phone')->label('Phone')->searchable(),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('price')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('starts_on')->label('Starts')->date()->sortable(),
                TextColumn::make('ends_on')->label('Ends')->date()->sortable(),
                TextColumn::make('credited_days')->label('Days credited'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state) => $state->label())
                    ->color(fn (SubscriptionStatus $state) => $state === SubscriptionStatus::Active ? 'success' : 'gray'),
                TextColumn::make('activator.name')->label('Activated by')->placeholder('Payment')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            ])
            ->recordActions([
                Action::make('renew')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn () => Guarded::can('subscriptions.activate'))
                    ->modalDescription('The new subscription starts the day after the current one ends, so she loses no days.')
                    ->schema([Select::make('plan_id')->label('Plan')->required()->options(fn () => static::planOptions())])
                    ->action(fn (Action $action, Subscription $record, array $data) => Guarded::run($action, fn () => app(ActivateSubscription::class)->handle(
                        $record->rider, Plan::query()->findOrFail($data['plan_id']), Guarded::staff(),
                    )))
                    ->successNotificationTitle('Renewed'),
                Action::make('absence')
                    ->label('Not riding')->icon(Heroicon::OutlinedNoSymbol)
                    ->visible(fn (Subscription $record) => Guarded::can('subscriptions.activate') && $record->status === SubscriptionStatus::Active)
                    ->modalHeading('She won\'t ride on a day')
                    ->modalDescription('Use this when she tells an operator. Whether the day is credited follows the rule her subscription was bought with.')
                    ->schema([DatePicker::make('date')->required()->minDate(fn () => today())->default(fn () => today()->addDay())])
                    ->action(fn (Action $action, Subscription $record, array $data) => Guarded::run($action, fn () => app(GiveAbsenceNotice::class)->handle(
                        $record->rider, Carbon::parse($data['date']),
                    )))
                    ->successNotificationTitle('Recorded'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubscriptions::route('/')];
    }
}
