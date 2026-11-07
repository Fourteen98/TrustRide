<?php

namespace Modules\Deliveries\Filament\Resources\DeliveryPlanResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Deliveries\Filament\Resources\DeliveryPlanResource;

class ManageDeliveryPlans extends ManageRecords
{
    protected static string $resource = DeliveryPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
