<?php

namespace Modules\Subscriptions\Filament\Resources\SubscriptionResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Modules\Riders\Models\Rider;
use Modules\Subscriptions\Actions\ActivateSubscription;
use Modules\Subscriptions\Enums\SeatStatus;
use Modules\Subscriptions\Filament\Resources\SubscriptionResource;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Seat;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Until Hubtel is wired in, an operator activates by hand. A payment will run the same Action.
            Action::make('activate')
                ->label('Activate a subscription')
                ->visible(fn () => Guarded::can('subscriptions.activate'))
                ->modalDescription('For a rider who has paid. Only riders with a reserved seat and no running subscription are listed.')
                ->schema([
                    Select::make('rider_id')->label('Rider')->required()->searchable()
                        ->options(fn () => Rider::query()
                            ->whereIn('id', Seat::query()->where('status', SeatStatus::Reserved)->select('rider_id'))
                            ->with('account')->orderBy('name')->get()
                            ->mapWithKeys(fn (Rider $rider) => [$rider->id => "{$rider->name}, {$rider->account->phone}"])),
                    Select::make('plan_id')->label('Plan')->required()->options(fn () => SubscriptionResource::planOptions()),
                ])
                ->action(fn (Action $action, array $data) => Guarded::run($action, fn () => app(ActivateSubscription::class)->handle(
                    Rider::query()->findOrFail($data['rider_id']), Plan::query()->findOrFail($data['plan_id']), Guarded::staff(),
                )))
                ->successNotificationTitle('Subscription active'),
        ];
    }
}
