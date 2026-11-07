<?php

namespace Modules\Deliveries\Filament\Resources\ZoneResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Deliveries\Filament\Resources\ZoneResource;

class ManageZones extends ManageRecords
{
    protected static string $resource = ZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
