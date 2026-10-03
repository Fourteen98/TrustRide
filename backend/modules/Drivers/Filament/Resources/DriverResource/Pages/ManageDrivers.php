<?php

namespace Modules\Drivers\Filament\Resources\DriverResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
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
            CreateAction::make()->using(fn (Action $action, array $data) => Guarded::run($action, fn () => app(RegisterDriver::class)->handle($data))),
        ];
    }
}
