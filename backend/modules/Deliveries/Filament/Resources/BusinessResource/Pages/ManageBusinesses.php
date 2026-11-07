<?php

namespace Modules\Deliveries\Filament\Resources\BusinessResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Deliveries\Actions\SaveBusiness;
use Modules\Deliveries\Filament\Resources\BusinessResource;

class ManageBusinesses extends ManageRecords
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(Guarded::calls(fn (array $data) => app(SaveBusiness::class)->handle(null, $data))),
        ];
    }
}
