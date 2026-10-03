<?php

namespace Modules\Access\Filament\Resources\AccountResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Access\Filament\Resources\AccountResource;
use Modules\Access\Models\Account;

class ManageAccounts extends ManageRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => AccountResource::save(new Account, $data)),
        ];
    }
}
