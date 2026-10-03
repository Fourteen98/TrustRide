<?php

namespace Modules\Drivers\Filament\Resources\VehicleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Drivers\Filament\Resources\VehicleResource;

class ManageVehicles extends ManageRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
