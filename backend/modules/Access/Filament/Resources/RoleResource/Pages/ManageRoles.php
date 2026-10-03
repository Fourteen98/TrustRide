<?php

namespace Modules\Access\Filament\Resources\RoleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Access\Filament\Resources\RoleResource;
use Modules\Access\Models\Role;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => RoleResource::save(new Role, $data)),
        ];
    }
}
