<?php

namespace Modules\Access\Filament\Resources\StaffResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Access\Filament\Resources\StaffResource;
use Modules\Access\Models\Staff;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => StaffResource::save(new Staff, $data)),
        ];
    }
}
