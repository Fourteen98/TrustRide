<?php

namespace Modules\Subscriptions\Filament\Resources\PlanResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Subscriptions\Actions\SaveSubscriptionSettings;
use Modules\Subscriptions\Filament\Resources\PlanResource;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rules')
                ->label('Renewal and seat rules')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->visible(fn () => Guarded::can('subscriptions.settings.manage'))
                ->modalDescription('These apply to every plan, from the next nightly run. They don\'t change what a rider has already been told.')
                ->fillForm(fn () => SaveSubscriptionSettings::current())
                ->schema(collect(SaveSubscriptionSettings::FIELDS)->map(
                    fn (array $field, string $key) => TextInput::make($key)->label($field[0])->numeric()->integer()->required()->minValue($field[1])->maxValue($field[2])
                )->values()->all())
                ->action(Guarded::calls(fn (array $data) => app(SaveSubscriptionSettings::class)->handle($data, Guarded::staff())))
                ->successNotificationTitle('Rules saved'),
            CreateAction::make(),
        ];
    }
}
