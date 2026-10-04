<?php

namespace Modules\Messaging\Filament\Resources\MessageResource\Pages;

use Filament\Resources\Pages\ManageRecords;
use Modules\Messaging\Filament\Resources\MessageResource;

class ManageMessages extends ManageRecords
{
    protected static string $resource = MessageResource::class;
}
