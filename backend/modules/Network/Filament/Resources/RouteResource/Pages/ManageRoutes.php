<?php

namespace Modules\Network\Filament\Resources\RouteResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Network\Filament\Resources\RouteResource;
use Modules\Network\Models\Route;

class ManageRoutes extends ManageRecords
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (Action $action, array $data) => RouteResource::save($action, new Route, $data)),
        ];
    }
}
