<?php

namespace Modules\Deliveries\Filament\Resources\DeliveryResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Deliveries\Actions\CreateDelivery;
use Modules\Deliveries\Enums\BusinessStatus;
use Modules\Deliveries\Filament\Resources\DeliveryResource;
use Modules\Deliveries\Models\Business;

class ManageDeliveries extends ManageRecords
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')->label('Send a parcel')->icon(Heroicon::OutlinedPlus)
                ->visible(fn () => Guarded::can('deliveries.parcels.manage'))
                ->schema([
                    Select::make('business_id')->label('From')->required()->searchable()
                        ->options(fn () => Business::query()->where('status', BusinessStatus::Active)->orderBy('name')->pluck('name', 'id')),
                    ...DeliveryResource::parcelFields(),
                ])
                ->action(Guarded::calls(fn (array $data) => app(CreateDelivery::class)->handle(Business::findOrFail($data['business_id']), $data, Guarded::staff())))
                ->successNotificationTitle('Parcel added. The customer has been told when to expect it.'),
        ];
    }
}
