<?php

namespace Modules\Drivers\Filament\Resources\DriverResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Drivers\Actions\RegisterDriver;
use Modules\Drivers\Filament\Resources\DriverResource;

class ManageDrivers extends ManageRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(Guarded::calls(fn (array $data) => app(RegisterDriver::class)->handle($data))),
        ];
    }
}
