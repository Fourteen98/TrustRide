<?php

namespace Modules\Network\Filament\Resources\StopResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Network\Filament\Resources\StopResource;

class ManageStops extends ManageRecords
{
    protected static string $resource = StopResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
