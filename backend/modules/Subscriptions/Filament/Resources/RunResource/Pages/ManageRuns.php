<?php

namespace Modules\Subscriptions\Filament\Resources\RunResource\Pages;

use App\Support\Filament\Guarded;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Subscriptions\Actions\SaveRun;
use Modules\Subscriptions\Filament\Resources\RunResource;

class ManageRuns extends ManageRecords
{
    protected static string $resource = RunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (Action $action, array $data) => Guarded::run($action, fn () => app(SaveRun::class)->handle($data))),
        ];
    }
}
