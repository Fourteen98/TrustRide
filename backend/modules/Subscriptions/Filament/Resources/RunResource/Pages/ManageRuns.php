<?php

namespace Modules\Subscriptions\Filament\Resources\RunResource\Pages;

use App\Support\Filament\Guarded;
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
            CreateAction::make()->using(Guarded::calls(fn (array $data) => app(SaveRun::class)->handle($data))),
        ];
    }
}
